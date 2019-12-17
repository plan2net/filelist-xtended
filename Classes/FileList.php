<?php
declare(strict_types=1);

namespace Plan2net\FilelistXtended;

use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFolderAccessPermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Utility\ListUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileList
 *
 * @package Plan2net\FilelistXtended
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class FileList extends \TYPO3\CMS\Filelist\FileList
{
    /**
     * @var bool
     */
    public $no_noWrap = true;

    /**
     * @var array
     */
    protected $displayFields = [];

    /**
     * @var array
     */
    protected $setFields = [];

    /**
     * Initialization of class
     *
     * @param Folder $folderObject The folder to work on
     * @param int $pointer Pointer
     * @param bool $sort Sorting column
     * @param bool $sortRev Sorting direction
     * @param bool $clipBoard
     * @param bool $bigControlPanel Show clipboard flag
     */
    public function start(Folder $folderObject, $pointer, $sort, $sortRev, $clipBoard = false, $bigControlPanel = false): void
    {
        parent::start($folderObject, $pointer, $sort, $sortRev, $clipBoard, $bigControlPanel);
        $this->displayFields = GeneralUtility::_GP('displayFields');
    }

    /**
     * Reading files and directories, counting elements and generating the list in ->HTMLcode
     */
    public function generateList()
    {
        $additionalFields = null;
        $this->setDispFields();
        if ($this->bigControlPanel && !empty($this->setFields['sys_file_metadata'])) {
            $additionalFields = implode(',', $this->setFields['sys_file_metadata']);
        }
        $this->HTMLcode .= $this->getTable('fileext,tstamp,size,rw,_REF_' . ($additionalFields ? ',' . $additionalFields : ''));
        if ($this->bigControlPanel) {
            $this->HTMLcode .= $this->fieldSelectBox('sys_file_metadata');
        }
    }


    /**
     * Returns a table with directories and files listed.
     *
     * @param string $rowlist List of fields
     * @return string HTML-table
     * @throws Exception
     */
    public function getTable($rowlist): string
    {
        // prepare space icon
        $this->spaceIcon = '<span class="btn btn-default disabled">' . $this->iconFactory->getIcon('empty-empty', Icon::SIZE_SMALL)->render() . '</span>';

        $storage = $this->folderObject->getStorage();
        $storage->resetFileAndFolderNameFiltersToDefault();

        // Only render the contents of a browsable storage
        if ($this->folderObject->getStorage()->isBrowsable()) {
            try {
                $foldersCount = $storage->countFoldersInFolder($this->folderObject);
                $filesCount = $storage->countFilesInFolder($this->folderObject);
            } catch (InsufficientFolderAccessPermissionsException $e) {
                $foldersCount = 0;
                $filesCount = 0;
            }

            if ($foldersCount <= $this->firstElementNumber) {
                $foldersFrom = false;
                $foldersNum = false;
            } else {
                $foldersFrom = $this->firstElementNumber;
                if ($this->firstElementNumber + $this->iLimit > $foldersCount) {
                    $foldersNum = $foldersCount - $this->firstElementNumber;
                } else {
                    $foldersNum = $this->iLimit;
                }
            }
            if ($foldersCount >= $this->firstElementNumber + $this->iLimit) {
                $filesFrom = false;
                $filesNum  = false;
            } else {
                if ($this->firstElementNumber <= $foldersCount) {
                    $filesFrom = 0;
                    $filesNum  = $this->iLimit - $foldersNum;
                } else {
                    $filesFrom = $this->firstElementNumber - $foldersCount;
                    if ($filesFrom + $this->iLimit > $filesCount) {
                        $filesNum = $filesCount - $filesFrom;
                    } else {
                        $filesNum = $this->iLimit;
                    }
                }
            }

            $folders = $storage->getFoldersInFolder($this->folderObject, $foldersFrom, $foldersNum, true, false, trim($this->sort), (bool)$this->sortRev);
            $files = $this->folderObject->getFiles($filesFrom, $filesNum, Folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, false, trim($this->sort), (bool)$this->sortRev);
            $this->totalItems = $foldersCount + $filesCount;
            // Adds the code of files/dirs
            $out = '';
            $titleCol = 'file';
            // Cleaning rowlist for duplicates and place the $titleCol as the first column always!
            $rowlist = '_LOCALIZATION_,' . $rowlist;
            $rowlist = GeneralUtility::rmFromList($titleCol, $rowlist);
            $rowlist = GeneralUtility::uniqueList($rowlist);
            $rowlist = $rowlist ? $titleCol . ',' . $rowlist : $titleCol;
            if ($this->clipBoard) {
                $rowlist = str_replace('_LOCALIZATION_,', '_LOCALIZATION_,_CLIPBOARD_,', $rowlist);
                $this->addElement_tdCssClass['_CLIPBOARD_'] = 'col-clipboard';
            }
            if ($this->bigControlPanel) {
                $rowlist = str_replace('_LOCALIZATION_,', '_LOCALIZATION_,_CONTROL_,', $rowlist);
                $this->addElement_tdCssClass['_CONTROL_'] = 'col-control';
            }
            $this->fieldArray = explode(',', $rowlist);

            // Add classes to table cells
            $this->addElement_tdCssClass[$titleCol] = 'col-title col-responsive';
            $this->addElement_tdCssClass['_LOCALIZATION_'] = 'col-localizationa';

            $folders = ListUtility::resolveSpecialFolderNames($folders);

            $iOut = '';
            // Directories are added
            $this->eCounter = $this->firstElementNumber;
            [, $code] = $this->fwd_rwd_nav();
            $iOut .= $code;

            $iOut .= $this->formatDirList($folders);
            // Files are added
            $iOut .= $this->formatFileList($files);

            $this->eCounter = $this->firstElementNumber + $this->iLimit < $this->totalItems
                ? $this->firstElementNumber + $this->iLimit
                : -1;
            [, $code] = $this->fwd_rwd_nav();
            $iOut .= $code;

            // Header line is drawn
            $theData = [];
            foreach ($this->fieldArray as $v) {
                if ($v === '_CLIPBOARD_' && $this->clipBoard) {
                    $cells = [];
                    $table = '_FILE';
                    $elFromTable = $this->clipObj->elFromTable($table);
                    if (!empty($elFromTable) && $this->folderObject->checkActionPermission('write')) {
                        $clipboardMode = $this->clipObj->clipData[$this->clipObj->current]['mode'] ?? '';
                        $permission = $clipboardMode === 'copy' ? 'copy' : 'move';
                        $addPasteButton = $this->folderObject->checkActionPermission($permission);
                        $elToConfirm = [];
                        foreach ($elFromTable as $key => $element) {
                            $clipBoardElement = $this->resourceFactory->retrieveFileOrFolderObject($element);
                            if ($clipBoardElement instanceof Folder && $clipBoardElement->getStorage()->isWithinFolder($clipBoardElement, $this->folderObject)) {
                                $addPasteButton = false;
                            }
                            $elToConfirm[$key] = $clipBoardElement->getName();
                        }
                        if ($addPasteButton) {
                            $cells[] = '<a class="btn btn-default t3js-modal-trigger"' .
                                ' href="' . htmlspecialchars($this->clipObj->pasteUrl(
                                    '_FILE',
                                    $this->folderObject->getCombinedIdentifier()
                                )) . '"'
                                . ' data-content="' . htmlspecialchars($this->clipObj->confirmMsgText(
                                    '_FILE',
                                    $this->path,
                                    'into',
                                    $elToConfirm
                                )) . '"'
                                . ' data-severity="warning"'
                                . ' data-title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_paste')) . '"'
                                . ' title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_paste')) . '">'
                                . $this->iconFactory->getIcon('actions-document-paste-into', Icon::SIZE_SMALL)
                                    ->render()
                                . '</a>';
                        } else {
                            $cells[] = $this->spaceIcon;
                        }
                    }
                    if ($this->clipObj->current !== 'normal' && $iOut) {
                        if ($this->folderObject->checkActionPermission('copy') && $this->folderObject->checkActionPermission('write') && $this->folderObject->checkActionPermission('move')) {
                            $cells[] = $this->linkClipboardHeaderIcon('<span title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_selectMarked')) . '">' . $this->iconFactory->getIcon('actions-edit-copy', Icon::SIZE_SMALL)->render() . '</span>', $table, 'setCB');
                        }
                        if ($this->folderObject->checkActionPermission('delete')) {
                            $cells[] = $this->linkClipboardHeaderIcon('<span title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_deleteMarked')) . '">' . $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL)->render(), $table, 'delete', $this->getLanguageService()->getLL('clip_deleteMarkedWarning'));
                        }
                        $onClick = 'checkOffCB(' . GeneralUtility::quoteJSvalue(implode(',', $this->CBnames)) . ', this); return false;';
                        $cells[] = '<a class="btn btn-default" rel="" href="#" onclick="' . htmlspecialchars($onClick) . '" title="' . htmlspecialchars($this->getLanguageService()->getLL('clip_markRecords')) . '">' . $this->iconFactory->getIcon('actions-document-select', Icon::SIZE_SMALL)->render() . '</a>';
                    }
                    $theData[$v] = implode('', $cells);
                } elseif ($v === '_REF_') {
                    $theData[$v] = htmlspecialchars($this->getLanguageService()->getLL('c_' . $v));
                } else {
                    // Normal row:
                    $columnLabel = $this->getLanguageService()->getLL('c_' . $v);
                    if (empty($columnLabel) && !empty($GLOBALS['TCA']['sys_file_metadata']['columns'][$v])) {
                        $columnLabel = rtrim($this->getLanguageService()->sL($GLOBALS['TCA']['sys_file_metadata']['columns'][$v]['label']), ':');
                    }
                    $theT = $this->linkWrapSort(htmlspecialchars($columnLabel), $this->folderObject->getCombinedIdentifier(), $v);
                    $theData[$v] = $theT;
                }
            }

            $out .= '<thead>' . $this->addElement(1, '', $theData, '', '', '', 'th') . '</thead>';
            $out .= '<tbody>' . $iOut . '</tbody>';
            // half line is drawn
            // finish
            $out = '
                <!--
                    Filelist table:
                -->
                <div class="panel panel-default">
                    <div class="table-fit">
                        <table class="table table-striped table-hover" id="typo3-filelist">
                            ' . $out . '
                        </table>
                    </div>
                </div>';
        } else {
            /** @var FlashMessage $flashMessage */
            $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $this->getLanguageService()->getLL('storageNotBrowsableMessage'), $this->getLanguageService()->getLL('storageNotBrowsableTitle'), FlashMessage::INFO);
            /** @var FlashMessageService $flashMessageService */
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            /** @var FlashMessageQueue $defaultFlashMessageQueue */
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
            $out = '';
        }

        return $out;
    }

    /**
     * This returns tablerows for the files in the array $items['sorting'].
     *
     * @param File[] $files File items
     * @return string HTML table rows.
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function formatFileList(array $files): string
    {
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $out = '';
        // first two keys are "0" (default) and "-1" (multiple), after that comes the "other languages"
        $allSystemLanguages = GeneralUtility::makeInstance(TranslationConfigurationProvider::class)->getSystemLanguages();
        $systemLanguages = array_filter($allSystemLanguages, function ($languageRecord) {
            return !($languageRecord['uid'] === -1 || $languageRecord['uid'] === 0 || !$this->getBackendUser()->checkLanguageAccess($languageRecord['uid']));
        });

        foreach ($files as $fileObject) {
            // Initialization
            $this->counter++;
            $this->totalbytes += $fileObject->getSize();
            $ext = $fileObject->getExtension();
            $fileName = trim($fileObject->getName());
            // The icon with link
            $theIcon = '<span title="' . htmlspecialchars($fileName . ' [' . (int)$fileObject->getUid() . ']') . '">'
                . $this->iconFactory->getIconForResource($fileObject, Icon::SIZE_SMALL)->render() . '</span>';
            $theIcon = BackendUtility::wrapClickMenuOnIcon($theIcon, 'sys_file', $fileObject->getCombinedIdentifier());
            // Preparing and getting the data-array
            $theData = [];
            foreach ($this->fieldArray as $field) {
                switch ($field) {
                    case 'size':
                        $theData[$field] = GeneralUtility::formatSize($fileObject->getSize(), htmlspecialchars($this->getLanguageService()->getLL('byteSizeUnits')));
                        break;
                    case 'rw':
                        $theData[$field] = '' . (!$fileObject->checkActionPermission('read') ? ' ' : '<strong class="text-danger">' . htmlspecialchars($this->getLanguageService()->getLL('read')) . '</strong>') . (!$fileObject->checkActionPermission('write') ? '' : '<strong class="text-danger">' . htmlspecialchars($this->getLanguageService()->getLL('write')) . '</strong>');
                        break;
                    case 'fileext':
                        $theData[$field] = strtoupper($ext);
                        break;
                    case 'tstamp':
                        $theData[$field] = BackendUtility::date($fileObject->getModificationTime());
                        break;
                    case '_CONTROL_':
                        $theData[$field] = $this->makeEdit($fileObject);
                        break;
                    case '_CLIPBOARD_':
                        $theData[$field] = $this->makeClip($fileObject);
                        break;
                    case '_LOCALIZATION_':
                        if (!empty($systemLanguages) && $fileObject->isIndexed() && $fileObject->checkActionPermission('editMeta') && $this->getBackendUser()->check('tables_modify', 'sys_file_metadata')) {
                            $metaDataRecord = $fileObject->_getMetaData();
                            $translations = $this->getTranslationsForMetaData($metaDataRecord);
                            $languageCode = '';

                            foreach ($systemLanguages as $language) {
                                $languageId = $language['uid'];
                                $flagIcon = $language['flagIcon'];
                                if (array_key_exists($languageId, $translations)) {
                                    $title = htmlspecialchars(sprintf($this->getLanguageService()->getLL('editMetadataForLanguage'), $language['title']));
                                    // @todo the overlay for the flag needs to be added ($flagIcon . '-overlay')
                                    $urlParameters = [
                                        'edit' => [
                                            'sys_file_metadata' => [
                                                $translations[$languageId]['uid'] => 'edit'
                                            ]
                                        ],
                                        'returnUrl' => $this->listURL()
                                    ];
                                    $flagButtonIcon = $this->iconFactory->getIcon($flagIcon, Icon::SIZE_SMALL, 'overlay-edit')->render();
                                    $url = (string)$uriBuilder->buildUriFromRoute('record_edit', $urlParameters);
                                    $languageCode .= '<a href="' . htmlspecialchars($url) . '" class="btn btn-default" title="' . $title . '">'
                                        . $flagButtonIcon . '</a>';
                                } else {
                                    $parameters = [
                                        'justLocalized' => 'sys_file_metadata:' . $metaDataRecord['uid'] . ':' . $languageId,
                                        'returnUrl' => $this->listURL()
                                    ];
                                    $returnUrl = (string)$uriBuilder->buildUriFromRoute('record_edit', $parameters);
                                    $href = BackendUtility::getLinkToDataHandlerAction(
                                        '&cmd[sys_file_metadata][' . $metaDataRecord['uid'] . '][localize]=' . $languageId,
                                        $returnUrl
                                    );
                                    $flagButtonIcon = '<span title="' . htmlspecialchars(sprintf($this->getLanguageService()->getLL('createMetadataForLanguage'), $language['title'])) . '">' . $this->iconFactory->getIcon($flagIcon, Icon::SIZE_SMALL, 'overlay-new')->render() . '</span>';
                                    $languageCode .= '<a href="' . htmlspecialchars($href) . '" class="btn btn-default">' . $flagButtonIcon . '</a> ';
                                }
                            }

                            // Hide flag button bar when not translated yet
                            $theData[$field] = ' <div class="localisationData btn-group" data-fileid="' . $fileObject->getUid() . '"' .
                                (empty($translations) ? ' style="display: none;"' : '') . '>' . $languageCode . '</div>';
                            $theData[$field] .= '<a class="btn btn-default filelist-translationToggler" data-fileid="' . $fileObject->getUid() . '">' .
                                '<span title="' . htmlspecialchars($this->getLanguageService()->getLL('translateMetadata')) . '">'
                                . $this->iconFactory->getIcon('mimetypes-x-content-page-language-overlay', Icon::SIZE_SMALL)->render() . '</span>'
                                . '</a>';
                        }
                        break;
                    case '_REF_':
                        $theData[$field] = $this->makeRef($fileObject);
                        break;
                    case 'file':
                        // Edit metadata of file
                        $theData[$field] = $this->linkWrapFile(htmlspecialchars($fileName), $fileObject);

                        if ($fileObject->isMissing()) {
                            $theData[$field] .= '<span class="label label-danger label-space-left">'
                                . htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:warning.file_missing'))
                                . '</span>';
                            // Thumbnails?
                        } elseif ($this->thumbs && ($this->isImage($ext) || $this->isMediaFile($ext))) {
                            $processedFile = $fileObject->process(
                                ProcessedFile::CONTEXT_IMAGEPREVIEW,
                                [
                                    'width' => $this->thumbnailConfiguration->getWidth(),
                                    'height' => $this->thumbnailConfiguration->getHeight()
                                ]
                            );
                            if ($processedFile) {
                                $thumbUrl = $processedFile->getPublicUrl(true);
                                $theData[$field] .= '<br /><img src="' . htmlspecialchars($thumbUrl) . '" ' .
                                    'width="' . $processedFile->getProperty('width') . '" ' .
                                    'height="' . $processedFile->getProperty('height') . '" ' .
                                    'title="' . htmlspecialchars($fileName) . '" alt="" />';
                            }
                        }
                        break;
                    default:
                        $theData[$field] = '';
                        if ($fileObject->hasProperty($field)) {
                            $metaDataRecord = $fileObject->_getMetaData();
                            $value = (string)BackendUtility::getProcessedValueExtra('sys_file_metadata', $field, $fileObject->getProperty($field), 200, $metaDataRecord['uid'], true, 0);
                            $theData[$field] = htmlspecialchars($value);
                        }
                }
            }
            $out .= $this->addElement(1, $theIcon, $theData);
        }
        return $out;
    }

    /**
     * Creates a checkbox list for selecting fields to display from a table:
     *
     * @param string $table Table name
     * @param bool   $formFields If TRUE, form-fields will be wrapped around the table.
     * @return string HTML table with the selector check box (name: displayFields['.$table.'][])
     */
    public function fieldSelectBox($table, $formFields = true): string
    {
        $lang = $this->getLanguageService();
        // Init:
        $formElements = ['', ''];
        if ($formFields) {
            $formElements = ['<form action="' . htmlspecialchars($this->listURL()) . '" method="post" name="fieldSelectBox">', '</form>'];
        }
        // Load already selected fields, if any:
        $setFields = is_array($this->setFields[$table]) ? $this->setFields[$table] : [];
        // Request fields from table:
        $fields = $this->makeFieldList($table, false, true);
        // Create a checkbox for each field:
        $checkboxes = [];
        $checkAllChecked = true;
        foreach ($fields as $fieldName) {
            // hide certain fields that make no sense in this list
            if (in_array($fieldName, [
                    'sys_language_uid',
                    'l10n_parent',
                    't3ver_label',
                    'fileinfo',
                    'file',
                    'uid',
                    'pid',
                    'tstamp',
                    'crdate',
                    'cruser_id'
                ])) {
                continue;
            }
            // Determine, if checkbox should be checked
            if ($fieldName === $this->fieldArray[0] || in_array($fieldName, $setFields, true)) {
                $checked = ' checked="checked"';
            } else {
                $checkAllChecked = false;
                $checked = '';
            }
            // Field label
            $fieldLabel = is_array($GLOBALS['TCA'][$table]['columns'][$fieldName])
                ? rtrim($lang->sL($GLOBALS['TCA'][$table]['columns'][$fieldName]['label']), ':')
                : '';
            $checkboxes[] = '<tr><td class="col-checkbox"><input type="checkbox" id="check-' . $fieldName . '" name="displayFields['
                . $table . '][]" value="' . $fieldName . '" ' . $checked
                . ($fieldName === $this->fieldArray[0] ? ' disabled="disabled"' : '') . '></td><td class="col-title">'
                . '<label class="label-block" for="check-' . $fieldName . '">' . htmlspecialchars($fieldLabel) . ' <span class="text-muted text-monospace">[' . htmlspecialchars($fieldName) . ']</span></label></td></tr>';
        }
        // Table with the field selector::
        $content = $formElements[0] . '
			<input type="hidden" name="displayFields[' . $table . '][]" value="">
			<div class="table-fit table-scrollable">
				<table border="0" cellpadding="0" cellspacing="0" class="table table-transparent table-hover">
					<thead>
						<tr>
							<th class="col-checkbox checkbox" colspan="2">
								<label><input type="checkbox" class="checkbox checkAll" ' . ($checkAllChecked ? ' checked="checked"' : '') . '>
								' . htmlspecialchars($lang->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:labels.toggleall')) . '</label>
							</th>
						</tr>
					</thead>
					<tbody>
					' . implode('', $checkboxes) . '
					</tbody>
				</table>
			</div>
			<input type="submit" name="search" class="btn btn-default" value="'
            . htmlspecialchars($lang->sL('LLL:EXT:lang/Resources/Private/Language/locallang_core.xlf:labels.setFields')) . '"/>
			' . $formElements[1];

        return '<div class="fieldSelectBox">' . $content . '</div>';
    }

    /**
     * Setting the field names to display in extended list.
     * Sets the internal variable $this->setFields
     */
    public function setDispFields(): void
    {
        $backendUser = $this->getBackendUser();
        // Getting from session:
        $dispFields = $backendUser->getModuleData('filelist/displayFields');
        // If fields have been given, then set those as the value and push it to session variable:
        if (is_array($this->displayFields)) {
            reset($this->displayFields);
            $tKey = key($this->displayFields);
            $dispFields[$tKey] = $this->displayFields[$tKey];
            $backendUser->pushModuleData('filelist/displayFields', $dispFields);
        }
        // Setting result:
        $this->setFields = $dispFields;
    }

    /**
     * Makes the list of fields to select for a table
     *
     * @param string $table Table name
     * @param bool $dontCheckUser If set, users access to the field (non-exclude-fields) is NOT checked.
     * @param bool $addDateFields If set, also adds crdate and tstamp fields (note: they will also be added if user is admin or dontCheckUser is set)
     * @return string[] Array, where values are fieldnames to include in query
     */
    public function makeFieldList($table, $dontCheckUser = false, $addDateFields = false): array
    {
        $backendUser = $this->getBackendUser();
        // Init fieldlist array:
        $fieldListArr = [];
        // Check table:
        if (is_array($GLOBALS['TCA'][$table]) && isset($GLOBALS['TCA'][$table]['columns']) && is_array(
                $GLOBALS['TCA'][$table]['columns']
            )) {
            // Traverse configured columns and add them to field array, if available for user.
            foreach ($GLOBALS['TCA'][$table]['columns'] as $fN => $fieldValue) {
                if ($dontCheckUser || ((!$fieldValue['exclude'] || $backendUser->check(
                                'non_exclude_fields',
                                $table . ':' . $fN
                            )) && $fieldValue['config']['type'] !== 'passthrough')) {
                    $fieldListArr[] = $fN;
                }
            }

            $fieldListArr[] = 'uid';
            $fieldListArr[] = 'pid';

            // Add date fields
            if ($dontCheckUser || $backendUser->isAdmin() || $addDateFields) {
                if ($GLOBALS['TCA'][$table]['ctrl']['tstamp']) {
                    $fieldListArr[] = $GLOBALS['TCA'][$table]['ctrl']['tstamp'];
                }
                if ($GLOBALS['TCA'][$table]['ctrl']['crdate']) {
                    $fieldListArr[] = $GLOBALS['TCA'][$table]['ctrl']['crdate'];
                }
            }
            // Add more special fields:
            if ($dontCheckUser || $backendUser->isAdmin()) {
                if ($GLOBALS['TCA'][$table]['ctrl']['cruser_id']) {
                    $fieldListArr[] = $GLOBALS['TCA'][$table]['ctrl']['cruser_id'];
                }
                if ($GLOBALS['TCA'][$table]['ctrl']['sortby']) {
                    $fieldListArr[] = $GLOBALS['TCA'][$table]['ctrl']['sortby'];
                }
                if (ExtensionManagementUtility::isLoaded(
                        'workspaces'
                    ) && $GLOBALS['TCA'][$table]['ctrl']['versioningWS']) {
                    $fieldListArr[] = 't3ver_id';
                    $fieldListArr[] = 't3ver_state';
                    $fieldListArr[] = 't3ver_wsid';
                }
            }
        } else {
            GeneralUtility::makeInstance(LogManager::class)
                ->getLogger(__CLASS__)
                ->error('TCA is broken for the table "' . $table . '": no required "columns" entry in TCA.');
        }

        return $fieldListArr;
    }
}
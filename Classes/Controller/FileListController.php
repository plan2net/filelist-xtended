<?php
declare(strict_types=1);

namespace Plan2net\FilelistXtended\Controller;

use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Class FileListController
 *
 * @package Plan2net\FilelistXtended\Controller
 * @author Wolfgang Klinger <wk@plan2.net>
 */
class FileListController extends \TYPO3\CMS\Filelist\Controller\FileListController
{
    /**
     * Initialize the view
     *
     * @param ViewInterface $view The view
     */
    protected function initializeView(ViewInterface $view): void
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        $pageRenderer = $this->view->getModuleTemplate()->getPageRenderer();
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/Recordlist/FieldSelectBox');
    }
}
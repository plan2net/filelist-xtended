<?php

defined('TYPO3_MODE') or die ('Access denied.');

(static function () {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Filelist\Controller\FileListController::class] = array(
        'className' => \Plan2net\FilelistXtended\Controller\FileListController::class
    );
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Filelist\FileList::class] = array(
        'className' => \Plan2net\FilelistXtended\FileList::class
    );
})();

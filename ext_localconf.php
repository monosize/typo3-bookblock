<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use FluidTYPO3\Flux\Core;

defined('TYPO3') or die();

// BookBlock Flux Content Element Extension
// Registriert das BookBlock Template für FluidTYPO3/Flux

// Register Flux Content Templates
$directory = ExtensionManagementUtility::extPath('bookblock', 'Resources/Private/Templates/Content/');
$templateFiles = glob($directory . "*.html");
if ($templateFiles) {
    foreach ($templateFiles as $template) {
        Core::registerTemplateAsContentType(
            'Monosize.Bookblock',
            $template
        );
    }
}


// Include TypoScript setup and constants
ExtensionManagementUtility::addTypoScriptSetup(
    '@import "EXT:bookblock/Configuration/TypoScript/setup.typoscript"'
);

ExtensionManagementUtility::addTypoScriptConstants(
    '@import "EXT:bookblock/Configuration/TypoScript/constants.typoscript"'
);

// Icon Registry für BookBlock
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
    'content-bookblock',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:bookblock/Resources/Public/Icons/content-bookblock.svg']
);
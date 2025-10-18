<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'ext-bookblock-wizard-icon' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:bookblock/Resources/Public/Icons/Extension.svg',
    ],
    'content-bookblock' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:bookblock/Resources/Public/Icons/content-bookblock.svg',
    ],
];

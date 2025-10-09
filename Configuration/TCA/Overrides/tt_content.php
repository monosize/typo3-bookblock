<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Fix pi_flexform duplication for Bookblock content element
// This runs after Flux has registered the content element
// Remove pi_flexform from after headers palette (general tab)
$GLOBALS['TCA']['tt_content']['types']['list']['showitem'] = str_replace(
    ['--palette--;;headers, pi_flexform,','--palette--;;headers,pi_flexform,'],
    '--palette--;;headers,',
    $GLOBALS['TCA']['tt_content']['types']['list']['showitem']
);

$a=1;

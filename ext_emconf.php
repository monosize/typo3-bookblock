<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'BookBlock Flux Content Element',
    'description' => 'Interactive BookBlock component for TYPO3 with PDF-to-image conversion and modern Stimulus controllers. Based on FluidTYPO3/Flux for TYPO3 13.4.',
    'category' => 'plugin',
    'author' => 'Gestaltende',
    'author_email' => 'info@gestaltende.de',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'extbase' => '13.4.0-13.4.99',
            'fluid' => '13.4.0-13.4.99',
            'flux' => '10.0.0-10.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'vhs' => '7.0.0-7.99.99',
        ],
    ],
];
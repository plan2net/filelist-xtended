<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'File list Xtended',
    'description' => 'Extends the core filelist with additional column options',
    'category' => 'backend',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wk@plan2.net',
    'state' => 'beta',
    'clearCacheOnLoad' => true,
    'author_company' => 'plan2net GmbH',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.5-9.5.99',
        ]
    ]
];

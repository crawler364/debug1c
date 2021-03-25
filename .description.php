<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => GetMessage('WC_DEBUG1C_COMPONENT_NAME'),
    'DESCRIPTION' => GetMessage('WC_DEBUG1C_COMPONENT_DESCRIPTION'),
    'CACHE_PATH' => 'N',
    'PATH' => [
        'ID' => GetMessage('WC_DEBUG1C_COMPONENT_DEV_NAME'),
        'NAME' => GetMessage('WC_DEBUG1C_COMPONENT_DEV_NAME'),
        'CHILD' => [
            'ID' => GetMessage('WC_DEBUG1C_COMPONENT_NAME'),
            'NAME' => GetMessage('WC_DEBUG1C_COMPONENT_NAME'),
        ],
    ],
];

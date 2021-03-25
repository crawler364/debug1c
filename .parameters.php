<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'GROUPS' => [
        'BASE' => [
            'NAME' => GetMessage('COMP_FORM_GROUP_PARAMS'),
        ],
    ],

    'PARAMETERS' => [
        'LOGIN' => [
            'NAME' => GetMessage('WC_DEBUG1C_LOGIN'),
            'TYPE' => 'STRING',
            'PARENT' => 'BASE',
        ],
        'PASSWORD' => [
            'NAME' => GetMessage('WC_DEBUG1C_PASSWORD'),
            'TYPE' => 'STRING',
            'PARENT' => 'BASE',
        ],
    ],
];

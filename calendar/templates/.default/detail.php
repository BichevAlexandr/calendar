<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$APPLICATION->IncludeComponent(
    'local:calendar.list',
    '.default',
    ''
);

$APPLICATION->IncludeComponent(
    'bitrix:ui.sidepanel.wrapper',
    '',
    [
        'POPUP_COMPONENT_NAME' => 'local:calendar.detail',
        'POPUP_COMPONENT_TEMPLATE_NAME' => '',
        'POPUP_COMPONENT_PARAMS' => [
            'EVENT_ID' => $arResult['VARIABLES']['EVENT_ID'],
        ],
        'USE_BACKGROUND_CONTENT' => false,
        'USE_PADDING' => true,
        'PLAIN_VIEW' => true,
        'RELOAD_GRID_AFTER_SAVE' => true,
        'PAGE_MODE' => false,
        'PAGE_MODE_OFF_BACK_URL' => $arParams['SEF_FOLDER'],
        'PAGE_WIDTH' => '614'
    ]
);

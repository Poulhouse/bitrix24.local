<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Генератор паролей в ЛК");

$APPLICATION->IncludeComponent(
    'bitrix:ui.sidepanel.wrapper',
    '',
    [
        'POPUP_COMPONENT_NAME' => 'kplab:transcripts.phones.statistic.detail',
        'POPUP_COMPONENT_TEMPLATE_NAME' => '',
        'POPUP_COMPONENT_PARAMS' => [
            "COMPONENT_POPUP_TEMPLATE_NAME" => "",
            "COMPONENT_PARAMS" => array("LIMIT" => "30")
        ]
    ]
);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
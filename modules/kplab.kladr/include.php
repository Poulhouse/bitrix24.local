<?php
/*
 * Файл local/modules/kplab.kladr/include.php
 */

use \Bitrix\Main\Page\Asset;
use \Bitrix\Main\Page\AssetLocation;

Class CKplabKladr
{
    public static function appendJavaScriptAndCSS()
    {
        $module_id = 'kplab.kladr';
        Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/jquery-3.2.1.min.js');
        Asset::getInstance()->addJs('/local/modules/' . $module_id . '/lib/js/onChangeScript.js', true);
        Asset::getInstance()->addJs('/local/modules/' . $module_id . '/lib/js/address-autocomplete.js', true);
        Asset::getInstance()->addCss('/bitrix/css/' . $module_id . '/suggestions.min.css');
        Asset::getInstance()->addCss('/local/modules/' . $module_id . '/lib/css/style.css');
        return true;
    }
    public static function appendScriptCrmUpdate($fields) {
        //print_r($fields);

        $module_id = 'kplab.kladr';
        Asset::getInstance()->addJs('/local/modules/' . $module_id . '/lib/js/onChange.js', true);
        return true;
    }
}
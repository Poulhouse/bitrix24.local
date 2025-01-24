<?php

namespace Skania;
class MyShortLink extends \CBXShortUri
{

    public static function create($url)
    {
        self::ClearErrors();
        $existingShortUri = self::GetShortUri($url);
        if (!empty($existingShortUri)) {
            return 'https://crm.seller-capital.ru' . $existingShortUri;
        }
        $shortUrl = 'custom-' . self::GenerateShortUri();

        $arFields = [
            'URI'       => $url,
            'SHORT_URI' => $shortUrl,
            'STATUS'    => 301,
        ];


        $id = self::Add($arFields);
        if ($id) {
            return 'https://crm.seller-capital.ru/' . $shortUrl;
        } else {
            return 'error';
        }
    }
}


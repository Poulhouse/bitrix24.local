<?php

namespace Skania;

use Bitrix\Im\Call\Conference;

class _Conference extends Conference {

    public static function createNewConference(string $title, int $userID): array {


        $password = _Conference::createPassword();

        $fields = [
            'broadcast_mode' => false,
            'id' => 0,
            'invintation' => "#CREATOR# приглашает вас в видеоконференцию:\n#TITLE#\nПрисоединяйтесь по ссылке: #LINK#",
            'password' => $password,
            'password_needed' => true,
            'presenters' => [
                $userID,
            ],
            'title' => $title,
            'users' => [
                $userID,
            ],
        ];


        $result = parent::add($fields);

        if (!$result->isSuccess()) {
            throw new \Exception("Failed to create conference: " . implode(', ', $result->getErrorMessages()));
        }

        $data = $result->getData();
        return [
            'LINK' => $data['ALIAS_DATA']['LINK'],
            'ID' => $data['ALIAS_DATA']['ID'],
            'PASSWORD' => $password,
        ];
    }

    public static function createPassword(){

        $length = 6;
        $password = '';
        $passwordCode = [
            '1', '2', '3', '4', '5', '6', '7', '8', '9', '0'
        ];
        for ($i = 0; $i < $length; $i++) {
            $password .= $passwordCode[random_int(0, count($passwordCode) - 1)];
        }

        return $password;

    }



}
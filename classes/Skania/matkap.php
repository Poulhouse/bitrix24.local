<?php namespace Skania;
use \Bitrix\Main\Loader;
Loader::includeModule("crm");
Loader::includeModule("socialnetwork");
Loader::includeModule('main');

class MatKap
{
    //region Работа с файлами
    public function getArchiveFromItemFiles(int $entityTypeID, int $entityID, string $fieldCode, string $type)
    {
        $elementData = \Bitrix\Crm\Service\Container::getInstance()
            ->getFactory($entityTypeID)->getItem($entityID);

        $fileData = $elementData->get($fieldCode) ?? ' ';
        if ($fileData && $fileData != ' ') {
            $archiveName = $_SERVER['DOCUMENT_ROOT'] . '/services_sodeistvie/MatKap/files/' . $type . $entityID . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($archiveName, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                die("Не удалось создать архив.");
            }
            foreach ($fileData as $fileId) {
                $fileArray = \CFile::GetFileArray($fileId);
                if ($fileArray && file_exists($_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'])) {
                    $zip->addFile($_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'], $fileArray['ORIGINAL_NAME']);
                }
            }
            $zip->close();
            $result = '/services_sodeistvie/MatKap/files/' . $type . $entityID . '.zip';
            return $result;
        }

    } // архивирует файлы определенного поля и возвращает ссылку


    public function getPdfFromImages(int $entityTypeID, int $entityID, string $sourceFieldCode, string $targetFieldCode) {

        require('/home/bitrix/www/services_sodeistvie/lib/fpdf185/fpdf.php');

        $item = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeID)->getItem($entityID);
        $files = $item->get($sourceFieldCode); // Получаем файлы

        $newFiles =[];

        foreach ($files as $fileId) {
            $fileArray = \CFile::GetFileArray($fileId);

            if (in_array($fileArray['CONTENT_TYPE'], ['image/jpeg', 'image/jpg', 'image/png'])) {
                $pdf = new \FPDF();
                $pdf->AddPage();

                $file = $_SERVER['DOCUMENT_ROOT'] . $fileArray['SRC'];
                $width = $fileArray['WIDTH'];
                $height = $fileArray['HEIGHT'];

                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();

                $scale = min($pageWidth / $width, $pageHeight / $height);

                $pdf->Image($file, 10, 10, $width * $scale, $height * $scale);

                $originalFileName = pathinfo($fileArray['SRC'], PATHINFO_FILENAME);
                $uploadFilePath = $_SERVER['DOCUMENT_ROOT'] . '/upload/tmp/' . $originalFileName . '.pdf';
                $pdf->Output($uploadFilePath, 'F');


                $fileArray = \CFile::MakeFileArray($uploadFilePath);
                $newFiles[] = $fileArray;

            }
        }

        $setFiles = $item->set($targetFieldCode, $newFiles);
        $compatible = $item->setFromCompatibleData([
            $targetFieldCode => $newFiles,
        ]);
        $result = $item->save();

    }




    //endregion Работа с файлами

    //region Комментарии таймлайна
    public function getPinCommentWithFiles(int $entityTypeID, int $entityID, string $text)
    {

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeID);
        $elementData = $factory->getItem($entityID);
        $pinTimelineID = $elementData->get('UF_CRM_ID_FIX_COMMENT'); // поле со значением закрепленного комментария
        if (empty($pinTimelineID) || (int)$pinTimelineID == 0) {
            $pinTimelineID = \Bitrix\Crm\Timeline\CommentEntry::create(
                [
                    'TEXT' => $text,
                    'SETTINGS' => [],
                    'AUTHOR_ID' => 1, //ID пользователя, от которого будет добавлен комментарий
                    'BINDINGS' => [['ENTITY_TYPE_ID' => $entityTypeID, 'ENTITY_ID' => $entityID, 'IS_FIXED' => 'Y']]
                ]
            );


            $elementData->set('UF_CRM_ID_FIX_COMMENT', $pinTimelineID);
            $saveResult = $elementData->save();
        } else {
            $updateResult = \Bitrix\Crm\Timeline\CommentEntry::update($pinTimelineID,
                [
                    'COMMENT' => $text,],
            );

            $unpinElement = \CRest::call('crm.timeline.item.unpin', [
                'id' => $pinTimelineID,
                'ownerTypeId' => $entityTypeID,
                'ownerId' => $entityID,
            ]);

        }

        $pinElement = \CRest::call('crm.timeline.item.pin', [
            'id' => $pinTimelineID,
            'ownerTypeId' => $entityTypeID,
            'ownerId' => $entityID,
        ]);


    } // создает закрепленный комментарий или обновляет существующий (создан для комментария со всеми файлами)

    public function fixTimeLineComment(int $entityTypeID, int $entityID, string $text)
    {
        $fixCommentId = \Bitrix\Crm\Timeline\CommentEntry::create(
            array(
                'TEXT' => $text,
                'SETTINGS' => [],
                'AUTHOR_ID' => 1, //ID пользователя, от которого будет добавлен комментарий
                'BINDINGS' => [['ENTITY_TYPE_ID' => $entityTypeID, 'ENTITY_ID' => $entityID, 'IS_FIXED' => 'N']]
            )
        );
        $pinElement = \CRest::call('crm.timeline.item.pin', [
            'id' => $fixCommentId,
            'ownerTypeId' => $entityTypeID,
            'ownerId' => $entityID,
        ]);
        return $fixCommentId;
    } // закрепляет комментарий в элементе

    public function unpinTimeLineComments(int $entityTypeID, int $entityID)
    {
        $elementData = \Bitrix\Crm\Service\Container::getInstance()
            ->getFactory($entityTypeID)->getItem($entityID);

        $pinTimelineID = $elementData->get('UF_CRM_ID_FIX_COMMENT') ?? '0'; // поле со значением закрепленного комментария
        $timelineBindings = \Bitrix\Crm\Timeline\Entity\TimelineBindingTable::getList([
        'filter' => [
            'ENTITY_TYPE_ID' => $entityTypeID,
            'ENTITY_ID' => $entityID,
            '!OWNER_ID' => $pinTimelineID,
            'IS_FIXED' => 'Y',
        ],
    ]);

    while ($binding = $timelineBindings->fetch()) {
        $ownerId = $binding['OWNER_ID'];

        $result = \Bitrix\Crm\Timeline\Entity\TimelineBindingTable::update(
            ['OWNER_ID' => $ownerId, 'ENTITY_ID' => $entityID, 'ENTITY_TYPE_ID' => $entityTypeID],
            ['IS_FIXED' => 'N']
        );
        $unpinElement = \CRest::call('crm.timeline.item.unpin', [
            'id' => $ownerId,
            'ownerTypeId' => $entityTypeID,
            'ownerId' => $entityID,
        ]);
    }

    } // открепляет все комментарии кроме комментария с ID = UF_CRM_ID_FIX_COMMENT

//endregion Комментарии таймлайна


    //region Партнеры
    public function addExtranetUser (string $fullName, string $name, string $middleName, string $email, string $phone, string $post)
    {

        $password = MatKap::createPassword();


        $data = [
            'LOGIN' => $email,
            'NAME' => $name,
            'LAST_NAME' => $fullName,
            'SECOND_NAME' => $middleName,
            'EMAIL' => $email,
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
            'ACTIVE' => 'Y',
            'GROUP_ID' => ['23',], //extranet
            'PERSONAL_PHONE' => $phone,
            'PERSONAL_MOBILE' => $phone,
            'WORK_PHONE' => $phone,
            'WORK_POSITION' => $post,
            'BLOCKED' => 'N',
        ];


        $user = new \CUser;

        $rsUser = \CUser::GetByLogin($email) ?? ' ';

        $createdUser = $rsUser->Fetch();


        if ($createdUser) { //пользователь уже существует
            $userID = $createdUser['ID'];

            $updateUser = $user->Update($userID, $data);
        }
        else {

            $userID = $user->Add($data);


            if ((int)$userID > 0) {

                $inviteGroup = new \CSocNetUserToGroup();
                $inviteMessage = $inviteGroup->SendRequestToJoinGroup(1, $userID, 39, ' '); // 162 - группа 123, 39 - содействие

            }
            else {
                echo 'что-то пошло не так';
            }

        }

        $message = $fullName . ' ' . $name . ' ' . $middleName . PHP_EOL . 'https://crm.seller-capital.ru/extranet' . PHP_EOL . 'Логин: ' .  $email . PHP_EOL . 'Пароль: ' . $password;

        return [
            'ID' => $userID,
            'MESSAGE' => $message,
        ];

    }

    public function createPassword(){

        $length = 11;
        $password = '';
        $passwordCode = [
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
            'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
            'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            '1', '2', '3', '4', '5', '6', '7', '8', '9', '0'
        ];
        for ($i = 0; $i < $length; $i++) {
            $password .= $passwordCode[random_int(0, count($passwordCode) - 1)];
        }

        return $password;

    }


    //endregion Партнеры



}
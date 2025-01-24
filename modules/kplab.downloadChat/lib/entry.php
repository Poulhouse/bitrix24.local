<?php namespace KPLab\Chat;

use \Bitrix\Main\Loader;
use \Bitrix\Im\V2\Chat;
use \Bitrix\Im\V2\Controller\Chat\Message;
use \Bitrix\Im\V2\MessageCollection;
use Bitrix\Im\V2\Rest\RestAdapter;
use Bitrix\Im\V2\Rest\RestConvertible;

Loader::includeModule("crm");
Loader::includeModule('im');

class Entry
{
	//users,files,messages

	protected const MAX_LIMIT = 300;
	protected const DEFAULT_LIMIT = 50;

	public static function download(int $firstUserId, int $secondUserId)
	{
		$idChat = CIMMessage ::GetChatId($firstUserId, $secondUserId);
		$chat = Chat ::getInstance($idChat);

		$filter = [];
		$order = ['id' => 'ASC'];
		$limit = 1000;

		$message = new Message;
		$_Messages = $message -> tailAction($chat, $filter, $order, $limit);
		//$_Messages = self::getMessages($messageFilter, $messageOrder, $limit);
		$usersName = $_Messages['users'][0]['lastName'] . " - " . $_Messages['users'][1]['lastName'];

		foreach ($_Messages as $k => $value)
		{
			if ($k == 'files')
			{
				foreach ($value as $chatFile)
				{
					$path = CFile ::GetPath($chatFile['viewerAttrs']['objectId']);
					self ::savePersonalPhotoByPath($path, $usersName, $chatFile['name']);
					CFile ::CopyFile($chatFile['viewerAttrs']['objectId'], false, "/kplab.downloadChat/" . $usersName . "/" . $chatFile['name']);
					$newMassages['files'][] = $path;
				}
			}
			if ($k == 'messages')
			{
				foreach ($value as $message)
				{
					//$newMassages['date'][] = ;
					$newMassages['text'][] = $message['text'] . " [" . $message['date'] . "]";
				}
			}
		}

		/*

				foreach ($_Messages['files'] as $chatFile) {
					CFile::CopyFile($chatFile['viewerAttrs']['objectId'],false,"/kplab.downloadChat/".$chatFile['name']);
					$newMassages['files'][] = CFile::GetPath($chatFile['viewerAttrs']['objectId']);
				}
		*/
		return $_Messages['files'];
		/*
				if($key == "") {
					return $_Messages;
				} else {
					return $newMassages;
				}
		*/
	}
}
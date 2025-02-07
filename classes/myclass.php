<?php

use Bitrix\Main\Application;
use \Bitrix\Main\Loader;
use Bitrix\Main\Page\Asset;
use \Bitrix\Location\Infrastructure\Service\Config\Container;
use \Bitrix\Location\Service;
use Bitrix\Location\Entity\Address\FieldCollection;
use \Bitrix\Location\Entity;
use KPLab\Logs;
use \Bitrix\Im\V2\Chat;
use \Bitrix\Im\V2\Controller\Chat\Message;
use \Bitrix\Im\V2\MessageCollection;
use Bitrix\Im\V2\Rest\RestAdapter;
use Bitrix\Im\V2\Rest\RestConvertible;

Loader::includeModule("crm");
Loader::includeModule("kplab.fias");
Loader::includeModule("location");
Loader::includeModule('iblock');
Loader::includeModule('im');
Loader::includeModule('socialnetwork');
Loader::includeModule('kplab.jwt');

define("LOG_MYCLASS", $_SERVER['DOCUMENT_ROOT']."/local/logs/myclass.log");

class MyClass
{
    public static function getDataOfForm($entityId, $entityTypeId): void
    {
        Logs\File::AddMessage([$entityId, $entityTypeId], "Сущность", LOG_MYCLASS);
        $linktocrm = '';

        switch ($entityTypeId) {
            case 1:
                $linktocrm = 'L_' . $entityId;
                break;
            case 2:
                $linktocrm = 'D_' . $entityId;
                break;
            case 3:
                $linktocrm = 'C_' . $entityId;
                break;
            case 4:
                $linktocrm = 'CO_' . $entityId;
                break;
            default:
                $EntityAbbreviation = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId)->getEntityAbbreviation();
                // Обработка случая, когда $entityTypeId не соответствует ни одному из известных значений
                // Можно добавить логирование или другую обработку ошибок
                $linktocrm = $EntityAbbreviation.'_'.$entityId;
                break;
        }

        $listActivity = \CCrmActivity::GetList([],['OWNER_TYPE_ID' => $entityTypeId,'OWNER_ID' => $entityId],false,false,[],[]);

        Logs\File::AddMessage($listActivity, "listActivity", LOG_MYCLASS);

        while ($activity = $listActivity->Fetch()) {
            if($activity['PROVIDER_ID'] == 'CRM_WEBFORM') {
                $fields = $activity['PROVIDER_PARAMS']['FIELDS'];
                $formProps = $activity['PROVIDER_PARAMS']['FORM'];
                $agreementsForm = $formProps['AGREEMENTS'];

                $formFields = [];
                $type = 6;

                $arFilter = array(
                    "IBLOCK_ID" => 16,
                    "CODE" => "TYPE" // Код вашего свойства типа "Список"
                );
                $rsPropsType = \CIBlockPropertyEnum::GetList(array(), $arFilter);
                while ($arPropType = $rsPropsType->Fetch()) {
                    $arTypeId[$arPropType["XML_ID"]] = $arPropType["ID"];
                    $arTypeName[$arPropType["XML_ID"]] = $arPropType["VALUE"];
                }

                foreach ($fields as $field) {
                    // Добавляем значение поля в массив
                    $formFields[$field['caption']] = isset($field['value'][0]) ? $field['value'][0] : '';
                }
                foreach($agreementsForm as $agreementForm) {
                    $agreement = new \Bitrix\Main\UserConsent\Agreement($agreementForm);
                    $agreementData = $agreement->getData();
                    $docName = $agreementData['NAME'];
                    $docLink = $agreementData['URL'];

                    $arProperties = [
                        'TYPE' => $arTypeId[$type],
                        'IP_ADDRESS' => $formProps['IP'],
                        'FORM_DATA' => json_encode($formFields,JSON_UNESCAPED_UNICODE),
                        'DOC_LINK' => $docLink,
                        'LINKTOCRM' => $linktocrm
                    ];
                    // Добавление нового элемента в инфоблок
                    $arFields = [
                        "IBLOCK_ID" => 16,
                        "NAME" => "Новое согласие $docName", // Название элемента
                        "ACTIVE" => "Y",
                        "PROPERTY_VALUES" => $arProperties
                    ];
                    $el = new \CIBlockElement;
                    $el->Add($arFields);
                }
            }
        }
    }

    public static function my_onMailMessageModified(&$event, &$fields, &$filter)
    {
        Logs\File::AddMessage($event, "event", LOG_MYCLASS);
        Logs\File::AddMessage($fields, "fields", LOG_MYCLASS);
        Logs\File::AddMessage($filter, "filter", LOG_MYCLASS);
    }
    public static function OnBeforeMailSend(&$arFields)
    {
        Logs\File::AddMessage($arFields, "arFields", LOG_MYCLASS);

        if(!str_contains($arFields['SUBJECT'], "deal_id ")) {
            $MessageId = $arFields['HEADER']['Message-Id'];

            preg_match('/crm\.activity\.(\d+)-/', $MessageId, $matches);
            if (isset($matches[1])) {
                Logs\File::AddMessage($matches[1], "ID дела", LOG_MYCLASS);
                $listActivity = CCrmActivity::GetList(
                    $arOrder = [],
                    $arFilter = [
                        'ID' => $matches[1]
                    ],
                    $arGroupBy = false,
                    $arNavStartParams = false,
                    $arSelectFields = [],
                    $arOptions = []
                );
                while ($activity = $listActivity->Fetch()) {
                    if($activity['OWNER_TYPE_ID'] == 2) {
                        $dealId = $activity['OWNER_ID'];

                        $arFields['SUBJECT'] = $arFields['SUBJECT'] . " deal_id ".$dealId;
                        Logs\File::AddMessage($arFields, "arFields new", LOG_MYCLASS);
                    }

                    //Logs\File::AddMessage($activity, "дело", LOG_MYCLASS);
                }
            }
        }
    }
    public static function OnBeforeChatMessageAddHandler(&$arMessageFields)
    {
        $chatId = $arMessageFields['TO_CHAT_ID'];
        $userId = $arMessageFields['AUTHOR_ID'];
        $groupId = 39;

        $userRole = CSocNetUserToGroup::GetUserRole($userId,$groupId);

        //Logs\File::AddMessage($arMessageFields, "arMessageFields", LOG_MYCLASS);


        if($userRole) {
            if ($chatId == 1464) {

                // Разрешаем отправку только модераторам и владельцам
                if ($userRole !== 'A' && $userRole !== 'E') {

                    CRest::call(
                        'im.notify.system.add',[
                            'USER_ID' => $userId,
                            'MESSAGE' => 'У вас нет прав для отправки сообщений в этом чате.'
                        ]
                    );
                    // Отменяем отправку сообщения
                    global $APPLICATION;
                    $APPLICATION->throwException("У вас нет прав для отправки сообщений в этом чате.");
                    return false;
                }
            }

        }
        return true;
    }

    public static function OnActivityAddHandler($activityId, $arFields): void
    {
        $companyId = 0;
        $bindings = $arFields['BINDINGS'];
        Logs\File::AddMessage($bindings, "bindings {$activityId} Activity", LOG_MYCLASS);

        foreach ($bindings as $binding) {
            if ($binding['OWNER_TYPE_ID'] == 1054) {
                break;
            }
            elseif ($binding['OWNER_TYPE_ID'] == \CCrmOwnerType::Company) {
                $companyId = $binding['OWNER_ID'];
                break;
            }
        }
        if (!$companyId) {
            return;
        }

        $factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $item = $factory->getItem($companyId);
        $SCP_itemId = $item->getData()['UF_CRM_SCP'] ?? 0;
        Logs\File::AddMessage($SCP_itemId, "SCP_itemId {$companyId} Company", LOG_MYCLASS);

        if (!$SCP_itemId) {
            return;
        }
        // Используем старое ядро для установки привязок

        $bindings[] = [
            'OWNER_TYPE_ID' => 1054, // Тип смарт-процесса SCP
            'OWNER_ID' => $SCP_itemId, // ID смарт-процесса
        ];

        \CCrmActivity::SaveBindings($activityId, $bindings, false, false, true);
    }

	public static function openForm($idZayavki) {
		$SERVER_NAME = $_SERVER['SERVER_NAME'];
		Logs\File::AddMessage($SERVER_NAME, "SERVER_NAME", LOG_MYCLASS);
		$url = "https://{$SERVER_NAME}/pub/site/53/crm_form_3qron/?idzayavki={$idZayavki}";
		self::open_in_new_tab($url);
	}
	public static function open_in_new_tab($url)
	{
		ob_end_clean(); // remove previous echoed data
		echo '<script>BX.ajax.onload_981865 = function() { window.open("' . $url . '", "_blank"); }</script>';
		die;
	}

	public static function getRQCompany($companyId, $type) {
		if($type === "ip") $PRESET_ID = 2;
		if($type === "fl") $PRESET_ID = 3;
		if($type === "ul") $PRESET_ID = 1;

		$requisite = \CRest::call(
			"crm.requisite.list",
			array("filter" => ["ENTITY_ID" => $companyId, "PRESET_ID" => $PRESET_ID], "select" => ['*'])
		)['result'];
		Logs\File::AddMessage($requisite, "requisite", LOG_MYCLASS);

		return $requisite[0];
	}

    public static function setRQToCompanyByInn($companyId, $inn) {

        $arParamsSearch = [
            'searchQuery' => (string) $inn,
            'options' => [
                'typeId' => "ITIN",
                'presetId' => "1"
            ]
        ];

        // Логика добавления реквизита по ИНН (вызывается API Bitrix24)
        $response = \CRest::call('crm.requisite.entity.search', $arParamsSearch)['result']['items'][0];
        Logs\File::AddMessage($response, "responseSearch", LOG_MYCLASS);

        $arParamsAdd = [
            'fields' => [
                'ENTITY_TYPE_ID' => 4,  // ID типа сущности (4 = компания)
                'ENTITY_ID' => (int) $companyId,
                'NAME' => $response['title'].", ".$response['subTitle'],
                'RQ_INN' => $response['fields']['RQ_INN'],
                'RQ_KPP' => $response['fields']['RQ_KPP'],
                'RQ_OGRN' => $response['fields']['RQ_OGRN'],
                'RQ_OKVED' => $response['fields']['RQ_OKVED'],
                'RQ_COMPANY_NAME' => $response['fields']['RQ_COMPANY_NAME'],
                'RQ_COMPANY_FULL_NAME' => $response['fields']['RQ_COMPANY_FULL_NAME'],
                'RQ_IFNS' => $response['fields']['RQ_IFNS'],
                'PRESET_ID' => $response['fields']['PRESET_ID'],
                'PRESET_COUNTRY_ID' => $response['fields']['PRESET_COUNTRY_ID'],
                'RQ_COMPANY_REG_DATE' => $response['fields']['RQ_COMPANY_REG_DATE'],
                'RQ_ADDR' => $response['fields']['RQ_ADDR'],
                'RQ_DIRECTOR' => $response['fields']['RQ_DIRECTOR']
            ]
        ];

        // Логика добавления реквизита по ИНН (вызывается API Bitrix24)
        $response = \CRest::call('crm.requisite.add', $arParamsAdd)['result'];
        Logs\File::AddMessage($response, "responseAdd", LOG_MYCLASS);
        
        return $response;
    }


	public static function MyOnPrologHandler()
	{
		global $USER, $APPLICATION;

        //region Для ЭКСТРАНЕТ пользовтелей
        /*$SERVER_NAME = $_SERVER['SERVER_NAME'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $arGroupExtranet = array(23);
        $arGroups = CUser::GetUserGroup($USER->GetID());
        $result_intersect = array_intersect($arGroupExtranet, $arGroups);// далее проверяем, если пользователь вошёл хотя бы в одну из групп, то позволяем ему что-либо делать

        if (!empty($result_intersect) && !str_contains($path, '/extranet')):
            header("Location: https://{$SERVER_NAME}/extranet/");
        endif;*/

        //endregion

		$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$result_intersect[] = null;
		$result_OtherIntersect[] = null;
		if(str_contains($path, "/crm/type/137/details/")) {
			$pathArray = explode('/crm/type/137/details/', $path);
			$MKID = str_replace("/","",$pathArray[1]);

			$link = '<a class="ui-btn ui-btn-primary" href="https://crm.seller-capital.ru/pub/site/53/crm_form_4iqsy/?MKID='.$MKID.'" target="_blank">Добавить участника</a>';
			\Bitrix\UI\Toolbar\Facade\Toolbar::addAfterTitleHtml($link);
		}


		if(str_starts_with($path, '/extranet') && str_contains($path, "/extranet/contacts/personal/user/")) {
			$arGroupExtranet = array(23);
			$arGroups = CUser::GetUserGroup($USER->GetID()); // массив групп, в которых состоит пользователь
			$pathArray = explode('/extranet/contacts/personal/user/', $path);
			$contactUserId = str_replace("/","",$pathArray[1]);
			$arOtherGroups = CUser::GetUserGroup($contactUserId); // массив групп, в которых состоит другой пользователь

			$result_OtherIntersect = array_intersect($arGroupExtranet, $arOtherGroups);// далее проверяем, если пользователь вошёл хотя бы в одну из групп, то позволяем ему что-либо делать
			$result_intersect = array_intersect($arGroupExtranet, $arGroups);// далее проверяем, если пользователь вошёл хотя бы в одну из групп, то позволяем ему что-либо делать
			if(isset($result_intersect) && $contactUserId !== $USER->GetID() && isset($result_OtherIntersect))
			{
				$SERVER_NAME = $_SERVER['SERVER_NAME'];
				Asset ::getInstance() -> addJs('/local/extranet/additional.js');
				Asset ::getInstance() -> addCss('/local/extranet/additional.css');
			}
		}
		if(str_starts_with($path, '/extranet') && str_contains($path, "/extranet/company/") && str_ends_with($path, 'company/'))
		{
			$SERVER_NAME = $_SERVER['SERVER_NAME'];
			$arGroupExtranet = array(23);
			$arGroups = CUser::GetUserGroup($USER->GetID());
			$result_intersect = array_intersect($arGroupExtranet, $arGroups);// далее проверяем, если пользователь вошёл хотя бы в одну из групп, то позволяем ему что-либо делать
			if (!empty($result_intersect)):
				header("Location: https://{$SERVER_NAME}/extranet/");
			endif;
		}
		if(str_starts_with($path, '/extranet') && str_contains($path, "/extranet/contacts/") && str_ends_with($path, 'contacts/'))
		{
			$SERVER_NAME = $_SERVER['SERVER_NAME'];
			$arGroupExtranet = array(23);
			$arGroups = CUser::GetUserGroup($USER->GetID());
			$result_intersect = array_intersect($arGroupExtranet, $arGroups);// далее проверяем, если пользователь вошёл хотя бы в одну из групп, то позволяем ему что-либо делать
			if (!empty($result_intersect)):
				header("Location: https://{$SERVER_NAME}/extranet/");
			endif;
		}
		if(str_starts_with($path, '/extranet') )
		{
			$arGroupExtranet = array(23);
			$arGroups = CUser::GetUserGroup($USER->GetID());
			$result_intersect = array_intersect($arGroupExtranet, $arGroups);// далее проверяем, если пользователь вошёл хотя бы в одну из групп, то позволяем ему что-либо делать
			$arOtherGroups = CUser::GetUserGroup($contactUserId);
		}
	}

	public static function generatePasswordActivity($phoneNumber = "000") {
		$dataG = array();
		$phone = "+".$phoneNumber;
		$data['phone'] = $phone;
		$data['password'] = "";

		$context = Application::getInstance()->getContext();
		$server = $context->getServer();
		$info = \KPLab\JWT\User::getId($data);
		Logs\File::AddMessage($info, "info", LOG_MYCLASS);

		if($info['TYPE'] == 'COMPANY') {
			$companyId = $info['ID'];
			$company = new \CCrmCompany(false);
			$psw = substr(preg_replace('~\D+~','',md5(md5(md5(md5(preg_replace('~\D+~','',$companyId)))))), 0, 6);
			$dataG['psw'] = $psw;
			$dataG['id'] = $companyId;
			$dataG['error'] = 'Ошибок нет';
			$dataG['status'] = "success";
		} elseif($info['TYPE'] == 'CONTACT') {
			$dataG['status'] = "error";
			$dataG['error'] = 'Указан неверный номер';
		} elseif($info['error']) {
			$dataG['status'] = "error";
			$dataG['error'] = 'Указан неверный номер';
		}
		return $dataG;
	}
	public static function generatePassword($phoneNumber) {
		$dataG = array();
		$phone = "+".$phoneNumber;
		$data['phone'] = $phone;

		$context = Application::getInstance()->getContext();
		$server = $context->getServer();
		$info = \KPLab\JWT\User::getId($data);

		if($info['TYPE'] == 'COMPANY') {
			$companyId = $info['ID'];
			$company = new \CCrmCompany(false);

			$psw = substr(preg_replace('~\D+~','',md5(md5(md5(md5(preg_replace('~\D+~','',$companyId)))))), 0, 6);

			$companyBU = \CRest::Call("crm.company.get",['id'=>$companyId])['result'];

			if(isset($companyBU['UF_CRM_1699900326048']) && $companyBU['UF_CRM_1699900326048'] !== '') {
				$arFields = [
					"UF_CRM_1697853788600" => $psw,
					"UF_CRM_1699900252754" => $server['REQUEST_TIME']
				];
			} else {
				$arFields = [
					"UF_CRM_1697853788600" => $psw,
					"UF_CRM_1699900252754" => $server['REQUEST_TIME']+1,
					"UF_CRM_1699900326048" => $server['REQUEST_TIME']
				];
			}

			$companyUpdate = \CRest::Call("crm.company.update",['id'=>$companyId,'fields'=>$arFields]);
			$companyAU = \CRest::Call("crm.company.get",['id'=>$companyId])['result'];
			$dataG['psw'] = $psw;
			$dataG['id'] = $companyId;
			$dataG['error'] = 'Ошибок нет';

		} elseif($info['TYPE'] == 'CONTACT') {
			$dataG['status'] = false;
			$dataG['error'] = 'Указан неверный номер';
		} elseif($info['error']) {
			$dataG['error'] = 'Указан неверный номер';
		}

		return json_encode($dataG);
	}

	public static function addressUpdate($id, $entityTypeId, $dataField, $nameField, $typeId) {
		global $DB;
		$Address = new \Bitrix\Location\Controller\Address;


		$resAddrList = CRest::call('crm.address.list', array(
			'filter' => array('ANCHOR_ID' => $id, 'ANCHOR_TYPE_ID' => $entityTypeId),
			'select' => array('TYPE_ID','ENTITY_TYPE_ID','ENTITY_ID','ANCHOR_ID','ANCHOR_TYPE_ID','LOC_ADDR_ID')
		))['result'];

		if(empty($resAddrList)) return false;

		foreach($resAddrList as $i => $addrItem){
			if($addrItem['TYPE_ID'] == $typeId)
			{
				$LOC_ADDR_ID = $addrItem['LOC_ADDR_ID'];

				$beforeStrSQL = "SELECT * FROM b_location_addr_fld WHERE ADDRESS_ID = ".$LOC_ADDR_ID;
				$beforeResults = $DB->Query($beforeStrSQL);

				if($dataField !== "" && $nameField == "STREET")
				{
					$streetBool = false;
					if (intval($beforeResults->SelectedRowsCount())>0)
					{
						while ($location_addr_fld = $beforeResults->Fetch()){
							if($location_addr_fld['TYPE'] == 340)
								$streetBool = true;
						}
					}

					$streetTMP = str_replace(" ", "", $dataField);
					$streetTMP = str_replace(".", "", $streetTMP);
					$streetTMP = str_replace(",", "", $streetTMP);
					$streetUPPER = strtoupper($streetTMP);

					if(!$streetBool)
					{
						$strSQL = "INSERT INTO b_location_addr_fld VALUES (".$LOC_ADDR_ID.",340,'".$dataField."','".$streetUPPER."')";
					} else
					{
						$strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$dataField."', VALUE_NORMALIZED = '".$streetUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 340";
					}
					$DB->Query($strSQL);

				}

				if($dataField !== "" && $nameField == "BUILDING")
				{
					$houseBool = false;
					if (intval($beforeResults->SelectedRowsCount())>0)
					{
						while ($location_addr_fld = $beforeResults->Fetch()){
							if($location_addr_fld['TYPE'] == 400)
								$houseBool = true;
						}

					}
					$houseTMP = str_replace(" ", "", $dataField);
					$houseTMP = str_replace(".", "", $houseTMP);
					$houseTMP = str_replace(",", "", $houseTMP);
					$houseUPPER = strtoupper($houseTMP);

					if(!$houseBool)
					{
						$strSQL = "INSERT INTO b_location_addr_fld VALUES (" . $LOC_ADDR_ID . ",400,'" . $dataField . "','" . $houseUPPER . "')";
					} else {
						$strSQL = "UPDATE b_location_addr_fld SET VALUE = '".$dataField."', VALUE_NORMALIZED = '".$streetUPPER."' WHERE ADDRESS_ID = ".$LOC_ADDR_ID." AND TYPE = 400";
					}

					$DB->Query($strSQL);
				}
			}
			$addrId = $Address->findById($addrItem['LOC_ADDR_ID']);
			$resAddress[$i] = $addrId['fieldCollection'];

			if(!empty($resAddress[$i][340]) && !empty($resAddress[$i][400])) {
				\CRest::call('crm.address.update',	array(
					'fields' => array(
						'TYPE_ID' => $addrItem['TYPE_ID'],
						'ENTITY_TYPE_ID' => $addrItem['ENTITY_TYPE_ID'],
						'ENTITY_ID' => $addrItem['ENTITY_ID'],
						'LOC_ADDR_ID' => $addrItem['LOC_ADDR_ID'],
						'POSTAL_CODE' => $resAddress[$i][50],//Почтовый индекс
						'COUNTRY' => $resAddress[$i][100],//Страна
						'PROVINCE' => $resAddress[$i][200],//Регион
						'REGION' => $resAddress[$i][210],//Район
						'CITY' => $resAddress[$i][300],//Город+Населенный пункт
						'STREET' => $resAddress[$i][340],//Улица
						'BUILDING' => $resAddress[$i][400],//Номер дома
						'ADDRESS_1' => $resAddress[$i][340].', '.$resAddress[$i][400],
						'ADDRESS_2' => $resAddress[$i][600]
					)
				));
			}
		}

		return true;
	}

	public static function createTemplateRQbyContact($contactId) {
		global $DB;
		$entityTypeId = \CCrmOwnerType::Contact;
		$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
		if (!$factory)
		{
			return 'factory not found';
		}
		$item = $factory->getItem($contactId);
		if (!$item)
		{
			return 'item not found';
		}
		$data = $item->getCompatibleData();
		$fields = [
			"ENTITY_TYPE_ID" => \CCrmOwnerType::Contact,
			"ENTITY_ID" => $item->getId(),
			"PRESET_ID" => 3,
			"NAME" => $data['FULL_NAME'],
			"RQ_LAST_NAME" => $data['LAST_NAME'],
			"RQ_FIRST_NAME" => $data['NAME'],
			"RQ_SECOND_NAME" => $data['SECOND_NAME'],
			"RQ_IDENT_DOC" => "Паспорт гражданина Российской Федерации",
			"ACTIVE" => "Y",
		];
		$requisiteID = \CRest::call("crm.requisite.add",['fields'=>$fields])['result'];
		Logs\File::AddMessage($requisiteID,"requisiteID", LOG_MYCLASS);

		// метод вернет данные об элементе в виде массива, идентичного по структуре "старому" API
		return $requisiteID;
	}
	public static function updateTemplateRQ($rqId) {
		return null;
	}

	public static function OnAfterCrmLeadUpdateHandler(&$arFields) {
		if($arFields["STATUS_ID"] == '14' && $arFields["MOVED_BY_ID"] !== 0 && $arFields["MODIFY_BY_ID"] !== 0) {
			//Logs\File::AddMessage($arFields,"ID: " . $arFields['ID'], LOG_MYCLASS);
			$MODIFY_BY_ID = $arFields["MODIFY_BY_ID"];
			$MOVED_BY_ID = $arFields["MOVED_BY_ID"];

			$entityTypeId = 1;
			$factory = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
			$item = $factory->getItem($arFields['ID']);
			if ($item)
			{

				$item -> set('MODIFY_BY_ID', "{$MODIFY_BY_ID}");
				$item -> set('MOVED_BY_ID', "{$MOVED_BY_ID}");
				$item -> set('ASSIGNED_BY_ID', "{$MODIFY_BY_ID}");
				$result = $item -> save();

				if ( !$result->isSuccess() ){
					/**
					 * Operation failed with error
					 *
					 * @operationResult->getErrors();
					 * @operationResult->getErrorMessages();
					 */
					$message = $result->getErrorMessages();
					\CRest::call('crm.timeline.comment.add',[
						'fields'=> [
							"ENTITY_ID" => $item->getId(),
							"ENTITY_TYPE" => "lead",
							"COMMENT" => print_r($message)
						]
					]);
				}
			}
		}
	}

	static function OnAfterCrm_UpdateHandler(&$arFields)
	{
	}

	public static function proverkaExistID($idLead, $r = false) {
		if(!$r) {
			$res = \CRest::call('crm.lead.list', array(
				'order' => ['ID' => 'ASC'],
				'filter' => [
					"=ID" => $idLead,
					">UF_CRM_1699947202" => 0
				],
				"select" => [
					"ID"
				]
			));
		} elseif($r) {
			$res = \CRest::call('crm.lead.list', array(
				'order' => ['ID' => 'ASC'],
				'filter' => [
					"=ID" => $idLead,
					">UF_CRM_1699962988" => 0
				],
				"select" => [
					"ID"
				]
			));
		}
		if($res["total"] > 0) {
			return true;
		} else {
			return false;
		}
	}

	public static function syncCRMOfLists_Lead($idLead, $idElement, $r = false) {
		if(!$r) {
			$res = \CRest::call('crm.lead.update',
				[
					'id' => $idLead,
					'fields' => ['UF_CRM_1699947202' => $idElement]
				]
			);
		} else {
			$res = \CRest::call('crm.lead.update',
				[
					'id' => $idLead,
					'fields' => ['UF_CRM_1699962988' => $idElement]
				]
			);
		}
		return $res;

	}

	public static function getItemsBySourceId(string $sourceId,int $limit) {
		global $DB;
		$result = [];
		$strLeadSQL = "SELECT * FROM b_crm_lead WHERE SOURCE_ID = ".$sourceId." ORDER BY ID ASC LIMIT ".$limit.";";
		$resLeadQuery = $DB->query($strLeadSQL);

		while($resLead = $resLeadQuery->Fetch()) {

			$result['LEADS'][] = $resLead['ID'];

		}

		return $result;
	}

	public static function getCurrentSourceIDByLeadId($LeadId) {
		global $DB;
		$strLeadSQL = "SELECT * FROM b_crm_lead WHERE ID = ".$LeadId." ORDER BY ID ASC ;";
		$resLeadQuery = $DB->query($strLeadSQL);
		$resLead = $resLeadQuery->Fetch();
		return $resLead['SOURCE_ID'];
	}

	public static function getOldSourceIDFromLeadTimeline($leadId) {
		global $DB;
		$strSQL = "SELECT * FROM b_crm_timeline WHERE ASSOCIATED_ENTITY_TYPE_ID = 1 AND ASSOCIATED_ENTITY_ID = ".$leadId." AND TYPE_ID = 2;";
		$resQuery = $DB->query($strSQL);
		while($res = $resQuery->Fetch()) {
			$st = str_replace("a:1:","",$res['SETTINGS']);
			$keywords = preg_split("/([\W]{1,100})/", $st,-1,PREG_SPLIT_NO_EMPTY);
			$result['SOURCE_ID'] = $keywords[count($keywords)-1];
		}
		return $result;
	}

	public static function updateLocationAddressByCRMId($ID) {
		global $DB;
		$resQuery_1 = $DB->query("SELECT LOC_ADDR_ID FROM b_crm_addr WHERE ANCHOR_ID = $ID");
		while ($row_1 = $resQuery_1->Fetch())
		{
			$LOC_ADDR_ID  = $row_1['LOC_ADDR_ID'];
			$resQuery = $DB->query("SELECT * FROM b_location_addr_fld WHERE ADDRESS_ID = $LOC_ADDR_ID");
			$array = [];
			//$array2 = [];
			while ($row = $resQuery->Fetch())
			{
				if($row['TYPE'] == 340) {
					$streetText = $row['VALUE'];
					$streetNormalized = $row['VALUE_NORMALIZED'];
				}
				if($row['TYPE'] == 400) {
					$houseText = $row['VALUE'];
					$houseNormalized = $row['VALUE_NORMALIZED'];
				}
			}

			$address_1Text = $streetText.", ".$houseText;
			$address_1Normalized = $streetNormalized.$houseNormalized;
			$array['VALUE'] = $address_1Text;
			$array['VALUE_NORMALIZED'] = $address_1Normalized;
			$DB->query("UPDATE b_location_addr_fld SET VALUE = '', VALUE_NORMALIZED = '' WHERE ADDRESS_ID = {$LOC_ADDR_ID} AND TYPE = 410");
			$DB->query("UPDATE b_location_addr_fld SET VALUE = '{$address_1Text}', VALUE_NORMALIZED = '{$address_1Normalized}' WHERE ADDRESS_ID = {$LOC_ADDR_ID} AND TYPE = 410");

			$DB->query("UPDATE b_crm_addr SET ADDRESS_1 = '{$address_1Text}' WHERE LOC_ADDR_ID = {$LOC_ADDR_ID}");
		}

		return $array;
	}

	//region Для Михаила

	const WORKDAY_BEGIN = [10, 0]; // 10:00
	const WORKDAY_END = [19, 0]; // 19:00
	public static function getProductionCalendar($periodDate) {
		$url = 'https://production-calendar.ru/get/ru/'.$periodDate.'/json';
		$curlOptions = [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
				"Content-Type: application/json"
			),
		];
		$ch = curl_init();
		curl_setopt_array($ch, $curlOptions);
		$response = curl_exec($ch);

		if ($response !== false)
		{
			$ch_info = curl_getinfo($ch);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header = substr($response, 0, $ch_info['header_size']);
			$html = substr($response, $ch_info['header_size']);

			$newHtml = substr($html,0,-1);
			$html = substr($newHtml,1);

			$jsonRes['success'] = $html;
			$jsonRes['error'] = "";

			if($http_code !== 200) {
				$jsonRes['error'] = $http_code." -- ".$header." -- ".$html;
				$jsonRes['success'] = "";
			}
			return json_decode(substr($response, $ch_info['header_size']),true)['days'];
		} else {
			$jsonRes['error'] = 'Нет соединения';
			$jsonRes['success'] = "";

			return $jsonRes;
		}
	}

	public static function fail_getWorkingDays(\DateTime $startDate, \DateTime $endDate){
		$newStartDate = $startDate->format('d.m.Y');
		$newEndDate = $endDate->format('d.m.Y');

		$period = $newStartDate."-".$newEndDate;
		$arDays = self::getProductionCalendar($period);

		$workDayBegin = (clone $startDate)->setTime(...self::WORKDAY_BEGIN);
		$workDayEnd = (clone $endDate)->setTime(...self::WORKDAY_END);

		$flawBegin = $startDate->diff($workDayBegin);
		$hoursDiffOfStart = $flawBegin->h;
		$minutesDiffOfStart = $flawBegin->i;
		$flawEnd = $workDayEnd->diff($endDate);
		$hoursDiffOfEnd = $flawEnd->h;
		$minutesDiffOfEnd = $flawEnd->i;
		$timeWorkingHours = 0;
		$cDays = count($arDays)-1;

		$firstDay = $arDays[0];
		$lastDay = $arDays[$cDays];

		$firstWorkingHours = 0;
		$lastWorkingHours = 0;

		foreach($arDays as $x => $day) {

			if($day['type_id'] == 1) {
				$countWorkingDays++;
				if($x == 0) {
					$firstWorkingMinutes = (60 - $minutesDiffOfStart);
					$firstWorkingHours = $day['working_hours'] - $hoursDiffOfStart;
					$timeWork += $firstWorkingHours + $firstWorkingMinutes/60;
				} elseif ($x == $cDays) {
					if($minutesDiffOfEnd !== 0) {
						$lastWorkingMinutes = (60 - $minutesDiffOfEnd);
						$hoursDiffOfEnd++;
					} else {
						$lastWorkingMinutes = 0;
					}
					$lastWorkingHours = $day['working_hours'] - $hoursDiffOfEnd + 1;
					$timeWork += $lastWorkingHours + $lastWorkingMinutes/60;
				} else {
					$timeWork += $day['working_hours'];
				}
			}
		}
		return $timeWork*60;
	}
	public static function getWorkingDays(\DateTime $startDate, \DateTime $endDate){

		$workStartHour = 10;
		$workStartMin = 0;
		$workEndHour = 19;
		$workEndMin = 0;
		$workdayHours = 8;
		$weekends = ['Saturday', 'Sunday'];
		$hours = 0;

		// Original start and end times, and their clones that we'll modify.
		$originalStart = new DateTime('04.02.2024 09:00:00');
		$start = clone $startDate;

		$end = clone $endDate;

		// Начиная с выходных? Вернитесь в будний день
		while (in_array($start->format('l'), $weekends))
		{
			$start->modify('-1 day')->setTime(23, 59);
		}

		// Заканчивается на выходных? Перейти к буднему дню.
		while (in_array($end->format('l'), $weekends))
		{
			$end->modify('midnight tomorrow');
		}

		// Дата начала после даты окончания? Может случиться, если начало и конец приходятся на одни и те же выходные (упс).
		if ($start > $end) throw new Exception('Start date is AFTER end date!');

		// Время выходит за рамки обычных рабочих часов? Если да, отрегулируйте.
		$startAdj = clone $start;

		if ($start < $startAdj->setTime($workStartHour, $workStartMin))
		{
			$start = $startAdj;
		} else if ($start >= $startAdj->setTime($workEndHour, $workEndMin))
		{
			//echo "Начало после закрытия этого дня, перенос на завтра."."\n";
			$start = $startAdj->setTime($workStartHour, $workStartMin)->modify('+1 day');
		} else {
			return self::fail_getWorkingDays($startDate, $endDate);
		}
		$endAdj = clone $end;

		if ($end > $endAdj->setTime($workEndHour, $workEndMin))
		{
			$end = $endAdj;
		}
		else if ($end < $endAdj->setTime($workStartHour, $workStartMin))
		{
			$end = $endAdj->setTime($workEndHour, $workEndMin)->modify('-1 day');
		}

		// Рассчитайте разницу между нашими модифицированными днями.
		$diff = $start->diff($end);

		// Пройдите каждый день, используя исходные значения, чтобы мы могли проверить выходные дни.
		$period = new DatePeriod($start, new DateInterval('P1D'), $end);

		foreach ($period as $day)
		{
			// Если это выходной день, вычтите его из общего количества дней в разнице.
			if (in_array($day->format('l'), ['Saturday', 'Sunday'])) $diff->d--;
		}
		if($diff->d < 0) {
			$diff->d = 0;
			$diff->h = 0;
		}

		// Calculate! Days * Hours in a day + hours + minutes converted to hours.
		$hours = ($diff->d * $workdayHours) + $diff->h;
		return   $hours*60 + $diff->i + round($diff->s/60, 0);
	}

	public static function getPathPersonalPhotoByUserId($userId) {
		$photoID = CUser::GetByID($userId)->Fetch()['PERSONAL_PHOTO']; //Получаем ID Фотографии по ID пользователя.
		$photoPath = CFile::GetPath($photoID); //Получаем путь к файлу.
		return $photoPath;
	}

	public static function savePersonalPhotoByPath($photoPath,$userName,$namefile = "") {
		Logs\File::AddMessage($photoPath,"путь к фото профиля {$userName}", LOG_MYCLASS);
		if($photoPath !== NULL) {
			$path_parts = pathinfo($photoPath);
			$newPath = $_SERVER["DOCUMENT_ROOT"] . "/local/src/userphotos/".$userName."/";
			if(!is_dir($newPath)) {
				mkdir($newPath, 0775);
			}
			if($namefile == "")
				$path = $newPath .$userName.".".$path_parts['extension'];
			else
				$path = $newPath .$namefile;

			if(!file_exists($path))
			{
				if(copy($_SERVER["DOCUMENT_ROOT"] . $photoPath, $path)) {
					return "Файл скопирован";
				} else {
					$errors= error_get_last();
					return "Файл не был скопирован! COPY ERROR: ".$errors['type']."<br />\n".$errors['message'];
				}
			} else {
				//return "Такой файл уже существует";
				$newpath = $_SERVER["DOCUMENT_ROOT"] . "local/src/userphotos/".$userName."/".$userName." (Copy)".".".$path_parts['extension'];
				if(copy($_SERVER["DOCUMENT_ROOT"] . $photoPath, $newpath)) {
					return "Такой файл уже существует был переименован и скопирован";
				} else {
					$errors= error_get_last();
					return "Такой файл уже существует был переименован, но не был скопирован! COPY ERROR: ".$errors['type']."<br />\n".$errors['message'];
				}
			}
		}
	}

	//endregion

	//region НАДО ПОЧИСТИТЬ

	public static function addLeadElement($idLead, $m=null) {
		$control = false;
		$statusValue = 565;
		$arResult = self::get_current_leadSellerByID($idLead);
		foreach ($arResult as $lead)
		{
			/* Отправляем имеющуюся информацию при подключении партнера в список - Селлеры лиды
			 * для этого сначала заполним массив $arrSeller полей (свойств) значениями
			 * 'NAME' - Название элемента списка - Наименование лида заполняем из сущности лида TITLE
			 * 'PROPERTY_ID_REFERRAL' => ID сущности компания или контакт - 914
			 * 'PROPERTY_LEAD_ID' => ИД Лида - 908
			 * 'PROPERTY_DATE_LEAD' => Дата лида - 909
			 * 'PROPERTY_DATA_DOGOVORA_ZAYMA' => Дата договора займа - 910
			 * 'PROPERTY_DATA_VYDACHI_DZ' => Дата выдачи ДЗ - 911
			 * 'PROPERTY_SUMMA_PO_DOGOVORU' => Сумма по договору - 912
			 * 'PROPERTY_STATUS' => Статус лида - 913
			 * 'PROPERTY_ASSIGN_LEAD' => Ответственный - 918
			 */

			//UF_CRM_1595501723401 - Запрашиваемая сумма
			//UF_CRM_63DD4A344C9B9 - ИНН
			//UF_CRM_1684985932 - Договор займа
			//UF_CRM_15_SS_NOMER - Номер ДЗ


			if ($lead['STATUS_ID'] == 26 || $lead['STATUS_ID'] == 28 || $lead['STATUS_ID'] == "UC_YVBJEK")
			{
				$control = true;
				$statusValue = 565; //Новый
			}
			if ($lead['STATUS_ID'] == 29 || $lead['STATUS_ID'] == "CONVERTED")
			{
				$control = true;
				$statusValue = 566; //В обработке
			}

			if ($lead['STATUS_ID'] == 27
				|| $lead['STATUS_ID'] == "UC_0EGR4U"
				|| $lead['STATUS_ID'] == "UC_IV8OD8"
				|| $lead['STATUS_ID'] == "JUNK"
				|| $lead['STATUS_ID'] == 5
				|| $lead['STATUS_ID'] == 3
				|| $lead['STATUS_ID'] == 9
				|| $lead['STATUS_ID'] == 16
				|| $lead['STATUS_ID'] == 25
				|| $lead['STATUS_ID'] == 30
				|| $lead['STATUS_ID'] == 41
			)
			{
				$control = true;
				$statusValue = 568; //Отказ
			}
			if ($control) {

				if ($lead['SOURCE_ID'] == 54)
				{
					$idReferral = 32813;
					$titleReferral = 'ООО "Вайлдберриз"';
					$assignReferral = 24376;
					$innReferral = '7721546864';
					$scpkbReferral = 2;
					$wb = true;

				}
                elseif ($lead['UTM_MEDIUM'] !== null && $lead['UTM_CONTENT'] !== null)
				{
					$innReferral = $lead['UTM_CONTENT'];
					$resReferral = self ::getReferralListByINN($innReferral)[0];

					//Получаем информацию сущностях компани и контакт привязанные к Партнеру-рефералу
					if ($resReferral['COMPANY_ID'] !== null)
					{
						$infoReferral = CRest ::call('crm.company.get', array("id" => $resReferral['COMPANY_ID']))['result'];
					} else
					{
						$infoReferral = CRest ::call('crm.contact.get', array("id" => $resReferral['CONTACT_ID']))['result'];
						$contact = true;
					}

					$assignReferral = $infoReferral['ASSIGNED_BY_ID'];
					$titleReferral = $infoReferral['TITLE'];

					$scpkbReferral = intval($infoReferral['UF_CRM_6442676E5B421']);
					$idReferral = $infoReferral['ID'];
				}

				$seller = self ::getSellerByReferralINN($innReferral, $lead['ID'], $wb);

				$idDZ = $seller['UF_CRM_1684985932'];

				if ($seller['SOURCE_ID'] == 54)
				{
					$seller['UF_CRM_643163EE701AE'] = $innReferral;
				}

				if ($idDZ !== null)
				{
					$DZ = self ::getSellerDZbyID($idDZ, $m);
					if($DZ['stageId'] == 'DT188_28:NEW') {
						if ($DZ['companyId'] !== null)
						{
							$infoSeller = \CRest ::call('crm.company.get', array("id" => $DZ['companyId']))['result'];
							$ENTITY_TYPE_ID = 4;
							$ENTITY_ID = $DZ['companyId'];

							$arOrders = array();
							$arFilters = array(
								"ENTITY_TYPE_ID" => $ENTITY_TYPE_ID,
								"ENTITY_ID" => $ENTITY_ID
							);
							$arSelect = array("ID","RQ_INN");
						} else
						{
							$infoSeller = \CRest ::call('crm.contact.get', array("id" => $DZ['contactId']))['result'];
							$ENTITY_TYPE_ID = 3;
							$ENTITY_ID = $DZ['contactId'];
							$contactSeller = true;

							$arOrders = array();
							$arFilters = array(
								"ENTITY_TYPE_ID" => $ENTITY_TYPE_ID,
								"ENTITY_ID" => $ENTITY_ID
							);
							$arSelect = array("ID","RQ_INN");
						}


						$resREQ = \CRest ::call('crm.requisite.list', array(
							'order' => $arOrders,
							'filter'=> $arFilters,
							'select' => $arSelect
						))['result'][0];

						$INN_Seller = $resREQ['RQ_INN'];

						$sumDZ = intval(str_replace('|RUB', '', $DZ['ufCrm15SsSummadogovora']));
						$numDZ = $DZ['ufCrm15SsNomer']; //NOMER_DOGOVORA
						$DateDZ = date('d.m.Y', strtotime($DZ['ufCrm15_1679925201'])); //Дата договора
						$DateVidachDZ = date('d.m.Y', strtotime($DZ['ufCrm15_1679907577'])); //Дата выдачи договора
						if($scpkbReferral > 0) {
							$sumSCPKB = $sumDZ * $scpkbReferral/100;
						} else {
							$sumSCPKB = 0;
						}
						$statusValue = 567;
					} else {
						$statusValue = 566;
					}
				}

				$arrSeller = array(
					'NAME' => $lead['TITLE'],
					'PROPERTY_908' => $lead['ID'],
					'PROPERTY_914' => $idReferral,
					'PROPERTY_909' => date('d.m.Y', strtotime($lead['DATE_CREATE'])),
					'PROPERTY_910' => $DateDZ, //Дата договора
					'PROPERTY_911' => $DateVidachDZ, //Дата выдачи договора
					'PROPERTY_912' => $sumDZ,
					'PROPERTY_913' => $statusValue,
					'PROPERTY_918' => $lead['ASSIGNED_BY_ID'],
					'PROPERTY_921' => $numDZ,
					'PROPERTY_922' => $scpkbReferral,
					'PROPERTY_923' => $sumSCPKB,
					'PROPERTY_924' => $INN_Seller,
					'PROPERTY_925' => $idDZ
				);

				$paramsForPostQuery = array(
					'IBLOCK_TYPE_ID' => 'lists',
					'IBLOCK_ID' => 167,
					'ELEMENT_CODE' => 'lead_' . $lead['ID'], //символьный код элемента
					'FIELDS' => $arrSeller
				);

				$paramsForGetQuery = array(
					'IBLOCK_TYPE_ID' => 'lists',
					'IBLOCK_ID' => 167,
					'ELEMENT_CODE' => 'lead_' . $idLead
				);

				$resultSeller = \CRest ::call('lists.element.get', $paramsForGetQuery);


				if ($resultSeller['total'])
				{
					$res = "Уже существует!";
				} else
				{
					$res = \CRest ::call('lists.element.add', $paramsForPostQuery);
				}
			}
		}
		return $resultSeller;
	}

	public static function updateLeadElement($idLead, $m=null) {

		$statusValue = 565;
		$control = false;
		$arResult = self::get_current_leadSellerByID($idLead);

		foreach ($arResult as $lead)
		{
			/* Отправляем имеющуюся информацию при подключении партнера в список - Селлеры лиды
			 * для этого сначала заполним массив $arrSeller полей (свойств) значениями
			 * 'NAME' - Название элемента списка - Наименование лида заполняем из сущности лида TITLE
			 * 'PROPERTY_ID_REFERRAL' => ID сущности компания или контакт - 914
			 * 'PROPERTY_LEAD_ID' => ИД Лида - 908
			 * 'PROPERTY_DATE_LEAD' => Дата лида - 909
			 * 'PROPERTY_DATA_DOGOVORA_ZAYMA' => Дата договора займа - 910
			 * 'PROPERTY_DATA_VYDACHI_DZ' => Дата выдачи ДЗ - 911
			 * 'PROPERTY_SUMMA_PO_DOGOVORU' => Сумма по договору - 912
			 * 'PROPERTY_STATUS' => Статус лида - 913
			 * 'PROPERTY_ASSIGN_LEAD' => Ответственный - 918
			 */

			//UF_CRM_1595501723401 - Запрашиваемая сумма
			//UF_CRM_63DD4A344C9B9 - ИНН
			//UF_CRM_1684985932 - Договор займа
			//UF_CRM_15_SS_NOMER - Номер ДЗ

			if ($lead['STATUS_ID'] == 26 || $lead['STATUS_ID'] == 28 || $lead['STATUS_ID'] == "UC_YVBJEK")
			{
				$control = true;
				$statusValue = 565;
			}
			if ($lead['STATUS_ID'] == 29 || $lead['STATUS_ID'] == "CONVERTED")
			{
				$control = true;
				$statusValue = 566;
			}

			if ($lead['STATUS_ID'] == 27
				|| $lead['STATUS_ID'] == "UC_0EGR4U"
				|| $lead['STATUS_ID'] == "UC_IV8OD8"
				|| $lead['STATUS_ID'] == "JUNK"
				|| $lead['STATUS_ID'] == 5
				|| $lead['STATUS_ID'] == 3
				|| $lead['STATUS_ID'] == 9
				|| $lead['STATUS_ID'] == 16
				|| $lead['STATUS_ID'] == 25
				|| $lead['STATUS_ID'] == 30
				|| $lead['STATUS_ID'] == 41
			)
			{
				$control = true;
				$statusValue = 568;
			}

			if($control) {
				if ($lead['SOURCE_ID'] == 54)
				{
					$idReferral = 32813;
					$titleReferral = 'ООО "Вайлдберриз"';
					$assignReferral = 24376;
					$innReferral = '7721546864';
					$scpkbReferral = 2;
					$wb = true;

				} elseif ($lead['UTM_MEDIUM'] !== null && $lead['UTM_CONTENT'] !== null)
				{
					$innReferral = $lead['UTM_CONTENT'];
					$resReferral = self ::getReferralListByINN($innReferral)[0];

					//$idReferral = $resReferral['COMPANY_ID'];
					//Получаем информацию сущностях компани и контакт привязанные к Партнеру-рефералу
					if ($resReferral['COMPANY_ID'] !== null)
					{
						$infoReferral = \CRest ::call('crm.company.get', array("id" => $resReferral['COMPANY_ID']))['result'];
					} else
					{
						$infoReferral = \CRest ::call('crm.contact.get', array("id" => $resReferral['CONTACT_ID']))['result'];
						$contact = true;
					}

					$assignReferral = $infoReferral['ASSIGNED_BY_ID'];
					$titleReferral = $infoReferral['TITLE'];
					$scpkbReferral = intval($infoReferral['UF_CRM_6442676E5B421']);
					$idReferral = $infoReferral['ID'];
				}

				$seller = self ::getSellerByReferralINN($innReferral, $lead['ID'], $wb);
				if ($seller['SOURCE_ID'] == 54) $seller['UF_CRM_643163EE701AE'] = $innReferral;
				if ($seller['UF_CRM_1684985932'] !== null)
				{
					$DZ = self ::getSellerDZbyID($seller['UF_CRM_1684985932'], $m);
					if($DZ['stageId'] == 'DT188_28:NEW') {
						if ($DZ['companyId'] !== null)
						{
							$infoSeller = \CRest ::call('crm.company.get', array("id" => $DZ['companyId']))['result'];
							$ENTITY_TYPE_ID = 4;
							$ENTITY_ID = $DZ['companyId'];

							$arOrders = array();
							$arFilters = array(
								"ENTITY_TYPE_ID" => $ENTITY_TYPE_ID,
								"ENTITY_ID" => $ENTITY_ID
							);
							$arSelect = array("ID","RQ_INN");
						} else
						{
							$infoSeller = \CRest ::call('crm.contact.get', array("id" => $DZ['contactId']))['result'];
							$ENTITY_TYPE_ID = 3;
							$ENTITY_ID = $DZ['contactId'];
							$contactSeller = true;

							$arOrders = array();
							$arFilters = array(
								"ENTITY_TYPE_ID" => $ENTITY_TYPE_ID,
								"ENTITY_ID" => $ENTITY_ID
							);
							$arSelect = array("ID","RQ_INN");
						}
						$resREQ = \CRest ::call('crm.requisite.list', array(
							'order' => $arOrders,
							'filter'=> $arFilters,
							'select' => $arSelect
						))['result'][0];
						$INN_Seller = $resREQ['RQ_INN'];
						$sumDZ = intval(str_replace('|RUB', '', $DZ['ufCrm15SsSummadogovora']));
						$numDZ = $DZ['ufCrm15SsNomer']; //NOMER_DOGOVORA
						$DateDZ = date('d.m.Y', strtotime($DZ['ufCrm15_1679925201'])); //Дата договора
						$DateVidachDZ = date('d.m.Y', strtotime($DZ['ufCrm15_1679907577'])); //Дата выдачи договора
						if($scpkbReferral > 0) {
							$sumSCPKB = $sumDZ * $scpkbReferral/100;
						} else {
							$sumSCPKB = 0;
						}
						$statusValue = 567;
					} else {
						$statusValue = 566;
					}
				}
				$arrSeller = array(
					'NAME' => $lead['TITLE'],
					'PROPERTY_908' => $lead['ID'],
					'PROPERTY_914' => $idReferral,
					'PROPERTY_909' => date('d.m.Y', strtotime($lead['DATE_CREATE'])),
					'PROPERTY_910' => $DateDZ, //Дата договора
					'PROPERTY_911' => $DateVidachDZ, //Дата выдачи договора
					'PROPERTY_912' => $sumDZ,
					'PROPERTY_913' => $statusValue,
					'PROPERTY_918' => $lead['ASSIGNED_BY_ID'],
					'PROPERTY_921' => $numDZ,
					'PROPERTY_922' => $scpkbReferral,
					'PROPERTY_923' => $sumSCPKB,
					'PROPERTY_924' => $INN_Seller,
					'PROPERTY_925' => $seller['UF_CRM_1684985932']
				);
				$paramsForPostQuery = array(
					'IBLOCK_TYPE_ID' => 'lists',
					'IBLOCK_ID' => 167,
					'ELEMENT_CODE' => 'lead_' . $lead['ID'], //символьный код элемента
					'FIELDS' => $arrSeller
				);

				$res = \CRest::call('lists.element.update', $paramsForPostQuery);
			}
		}
		return $res;
	}

	public static function get_current_dealReferral($idReferralDeal) {
		return \CRest ::call('crm.deal.list', array(
			"filter" => ["CATEGORY_ID"=>22, "STAGE_ID"=>"C22:WON", "!==UF_CRM_63DD4A344C9B9"=>null, "ID"=>$idReferralDeal], //
			"select" => [
				"ID",
				"LEAD_ID",
				"COMPANY_ID", //REFERRAL ID COMPANY
				"CONTACT_ID", //REFERRAL ID CONTACT
				"UF_CRM_63DD4A344C9B9", //ИНН (SCP) актуальный
				"UF_CRM_63E0B06AF31D0", //Реферальная ссылка
				"UF_CRM_644267744B214", //SCP-KB
				"UF_CRM_4_QRL_PHOTO", //QR-Код
				"UF_CRM_63D8C56F039BA" //Правовая форма
			]
		))['result'];
	}

	public static function get_current_dealSeller($idSellerDeal) {
		return \CRest ::call('crm.deal.list', array(
			"order" => ["ID"=>"ASC"],
			"filter" => [
				"CATEGORY_ID" => 23,
				"STAGE_ID" => "C23:WON",
				"ID"=>$idSellerDeal
			],
			"select" => [
				"LEAD_ID",
				"COMPANY_ID",
				"CONTACT_ID",
				"UTM",
				"UF_CRM_1663045014",
				"UF_CRM_1684985932",
				"UF_CRM_643163EE701AE"
			]
		))['result'];
	}

	public static function updateReferralElement($idReferralDeal) {
		$wb = false;
		$resElementUpdate = "";

		$dealReferral = self::get_current_dealReferral($idReferralDeal)[0];

		//Получаем информацию сущностях компани и контакт привязанные к Партнеру-рефералу
		if($dealReferral['COMPANY_ID'] !== '0') {
			$infoReferral = \CRest::call('crm.company.get', array("id" => $dealReferral['COMPANY_ID']))['result'];
			$titleReferral = $infoReferral['TITLE'];
		} else {
			$infoReferral = \CRest::call('crm.contact.get', array("id" => $dealReferral['CONTACT_ID']))['result'];
			$titleReferral = $infoReferral['LAST_NAME'].' '.$infoReferral['NAME'].' '.$infoReferral['SECOND_NAME'];
		}

		$assignReferral = $infoReferral['ASSIGNED_BY_ID'];
		$idReferral = $infoReferral['ID'];
		if($idReferral == 32813) $wb = true;

		$qtyLeads = self::getQTYLeadsByReferral(
			array(
				'inn'=>$dealReferral['UF_CRM_63DD4A344C9B9'],
				'wb'=>$wb
			)
		);
		$qtyDogovorZaim = self::getQTYSellerByCompanyINN(
			array(
				'innReferral'=>$dealReferral['UF_CRM_63DD4A344C9B9'],
				'wb'=>$wb
			)
		);
		$sumDogovorZaim = self::getSUMSellerByCompanyINN(
			array(
				'innReferral'=>$dealReferral['UF_CRM_63DD4A344C9B9'],
				'wb'=>$wb
			)
		);

		$sumSCP_KB = self::getSUMSCPKBSellerByCompanyINN(
			array(
				'innReferral'=>$dealReferral['UF_CRM_63DD4A344C9B9'],
				'scp_kb' => $dealReferral['UF_CRM_644267744B214'],
				'wb'=>$wb
			)
		);

		/* Отправляем имеющуюся информацию при подключении партнера в список - Партнёры-рефералы
		 * для этого сначала заполним массив $arrReferral полей (свойств) значениями
		 * 'NAME' - Название элемента списка - Юридическое наименование партнера заполняем из сущности компания или
		 *  контакт
		 * 'PROPERTY_ID_REFERRAL' => ID сущности компания или контакт 895
		 * 'PROPERTY_INN_REFERRAL' => ИНН Партнёра из карточки сделки 896
		 * 'PROPERTY_REFERRAL_LINK' => Реферальная ссылка Партнёра из карточки сделки 917
		 * 'PROPERTY_SCP_KB' => SCP-KB Партнёра из карточки сделки 903
		 * 'PROPERTY_QR_KOD' => QR-Код Партнёра из карточки сделки 916
		 * 'PROPERTY_ASSIGN' => Ответственный Партнёра из карточки компании или контакта 898
		 * 'PROPERTY_QR_LINK' => QR Link Партнёра из карточки сделки 919
		 * 'PROPERTY_SUM_SCP_KB' => Сумма SCP-КВ - вычисляемый параметр SCP_KB * Сумма сделок - 904
		 * 'PROPERTY_SUM_DEALS' => Сумма сделок - 905
		 * 'PROPERTY_COUNT_LEADS' => Всего лидов - 902
		 * 'PROPERTY_COUNT_DEALS' => Всего сделок - 915
		 * 'PROPERTY_ID_SDELKI_SCP' => ID Карточки сделки Партнёра - 920
		 */

		if(is_array($dealReferral['UF_CRM_4_QRL_PHOTO'])) {
			$QRID = $dealReferral['UF_CRM_4_QRL_PHOTO']['id'];
			$pathFileQR = CFile::GetPath($QRID);
			$linkQR = "https://".$_SERVER['SERVER_NAME'].$pathFileQR;
		} else {
			$linkQR = "";
		}
		$arrReferral = array(
			'NAME' => $titleReferral,
			'PROPERTY_895' => $idReferral,
			'PROPERTY_896' => $dealReferral['UF_CRM_63DD4A344C9B9'],
			'PROPERTY_917' => $dealReferral['UF_CRM_63E0B06AF31D0'],
			'PROPERTY_903' => $dealReferral['UF_CRM_644267744B214'],
			'PROPERTY_916' => $dealReferral['UF_CRM_4_QRL_PHOTO'],
			'PROPERTY_898' => $assignReferral,
			'PROPERTY_920' => $dealReferral['ID'],
			'PROPERTY_919' => $linkQR,
			'PROPERTY_904' => "{$sumSCP_KB}.00|RUB",
			'PROPERTY_905' => "{$sumDogovorZaim}.00|RUB",
			'PROPERTY_902' => intVal($qtyLeads),
			'PROPERTY_915' => $qtyDogovorZaim
		);

		$paramsForPostQuery = array(
			'IBLOCK_TYPE_ID' => 'lists',
			'IBLOCK_ID' => 166,
			'ELEMENT_CODE' => 'referral_' . $idReferral, //символьный код элемента
			'FIELDS' => $arrReferral
		);
		$resElementUpdate = \CRest::call('lists.element.update', $paramsForPostQuery);

		return $resElementUpdate;
	}

	public static function addReferralElement($idDeal) {
		$wb = false;
		$resElementAdd = "";

		$dealReferral = self::get_current_dealReferral($idDeal)[0];

		//Получаем информацию сущностях компани и контакт привязанные к Партнеру-рефералу
		if($dealReferral['COMPANY_ID'] !== '0') {
			$infoReferral = \CRest::call('crm.company.get', array("id" => $dealReferral['COMPANY_ID']))['result'];
			$titleReferral = $infoReferral['TITLE'];
			//AddMessage2Log($infoReferral,"infoReferral Company");
		} else {
			$infoReferral = \CRest::call('crm.contact.get', array("id" => $dealReferral['CONTACT_ID']))['result'];
			//AddMessage2Log($infoReferral,"infoReferral Contact");
			$titleReferral = $infoReferral['LAST_NAME'].' '.$infoReferral['NAME'].' '.$infoReferral['SECOND_NAME'];
		}



		$assignReferral = $infoReferral['ASSIGNED_BY_ID'];
		$idReferral = $infoReferral['ID'];

		if($idReferral == 32813) $wb = true;

		$qtyLeads = self::getQTYLeadsByReferral(
			array(
				'inn'=>$dealReferral['UF_CRM_63DD4A344C9B9'],
				'wb'=>$wb
			)
		);
		$qtyDogovorZaim = self::getQTYSellerByCompanyINN(
			array(
				'innReferral'=>$dealReferral['UF_CRM_63DD4A344C9B9'],
				'wb'=>$wb
			)
		);

		$sumDogovorZaim = self::getSUMSellerByCompanyINN(
			array(
				'innReferral'=>$dealReferral['UF_CRM_63DD4A344C9B9'],
				'wb'=>$wb
			)
		);
		$sumSCP_KB = self::getSUMSCPKBSellerByCompanyINN(
			array(
				'innReferral'=>$dealReferral['UF_CRM_63DD4A344C9B9'],
				'scp_kb' => $dealReferral['UF_CRM_644267744B214'],
				'wb'=>$wb
			)
		);

		/* Отправляем имеющуюся информацию при подключении партнера в список - Партнёры-рефералы
		 * для этого сначала заполним массив $arrReferral полей (свойств) значениями
		 * 'NAME' - Название элемента списка - Юридическое наименование партнера заполняем из сущности компания или
		 *  контакт
		 * 'PROPERTY_ID_REFERRAL' => ID сущности компания или контакт 895
		 * 'PROPERTY_INN_REFERRAL' => ИНН Партнёра из карточки сделки 896
		 * 'PROPERTY_REFERRAL_LINK' => Реферальная ссылка Партнёра из карточки сделки 917
		 * 'PROPERTY_SCP_KB' => SCP-KB Партнёра из карточки сделки 903
		 * 'PROPERTY_QR_KOD' => QR-Код Партнёра из карточки сделки 916
		 * 'PROPERTY_ASSIGN' => Ответственный Партнёра из карточки компании или контакта 898
		 * 'PROPERTY_QR_LINK' => QR Link Партнёра из карточки сделки 919
		 * 'PROPERTY_SUM_SCP_KB' => Сумма SCP-КВ - вычисляемый параметр SCP_KB * Сумма сделок - 904
		 * 'PROPERTY_SUM_DEALS' => Сумма сделок - 905
		 * 'PROPERTY_COUNT_LEADS' => Всего лидов - 902
		 * 'PROPERTY_COUNT_DEALS' => Всего сделок - 915
		 * 'PROPERTY_ID_SDELKI_SCP' => ID Карточки сделки Партнёра - 920
		 */

		if(is_array($dealReferral['UF_CRM_4_QRL_PHOTO'])) {
			$QRID = $dealReferral['UF_CRM_4_QRL_PHOTO']['id'];
			$pathFileQR = CFile::GetPath($QRID);
			$linkQR = "https://".$_SERVER['SERVER_NAME'].$pathFileQR;
		} else {
			$linkQR = "";
		}

		$arrReferral = array(
			'NAME' => $titleReferral,
			'PROPERTY_895' => $idReferral,
			'PROPERTY_896' => $dealReferral['UF_CRM_63DD4A344C9B9'],
			'PROPERTY_917' => $dealReferral['UF_CRM_63E0B06AF31D0'],
			'PROPERTY_903' => $dealReferral['UF_CRM_644267744B214'],
			'PROPERTY_916' => $dealReferral['UF_CRM_4_QRL_PHOTO'],
			'PROPERTY_898' => $assignReferral,
			'PROPERTY_920' => $dealReferral['ID'],
			'PROPERTY_919' => $linkQR,
			'PROPERTY_904' => "{$sumSCP_KB}.00|RUB",
			'PROPERTY_905' => "{$sumDogovorZaim}.00|RUB",
			'PROPERTY_902' => intVal($qtyLeads),
			'PROPERTY_915' => $qtyDogovorZaim
		);

		$paramsForPostQuery = array(
			'IBLOCK_TYPE_ID' => 'lists',
			'IBLOCK_ID' => 166,
			'ELEMENT_CODE' => 'referral_' . $idReferral, //символьный код элемента
			'FIELDS' => $arrReferral
		);
		$resElementAdd = \CRest::call('lists.element.add', $paramsForPostQuery);

		return $resElementAdd;
	}

	public static function getQTYSellerByCompanyIDWithoutWB($innReferral){
		$res = \CRest ::call('crm.deal.list', array(
			"order" => ["ID"=>"ASC"],
			"filter" => [
				"CATEGORY_ID" => 23,
				"STAGE_ID" => "C23:WON",
				"UF_CRM_643163EE701AE" => $innReferral
			],
			"select" => [
				"ID",
				"TITLE",
				"UF_CRM_643163EE701AE",
				"LEAD_ID",
				"UF_CRM_1684985932"
			]
		))['result'];
		return $res;
	}

	public static function getQTYSellerByCompanyINN($params = array()) {
		$innReferral = $params['innReferral'];
		$wb = $params['wb'];
		if(!$wb){
			$res = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID" => 23,
					"STAGE_ID" => "C23:WON",
					"=UF_CRM_643163EE701AE" => $innReferral
				],
				"select" => [
					"ID"
				]
			))['total'];
		} else {
			$res = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID" => 23,
					"STAGE_ID" => "C23:WON",
					"=SOURCE_ID" => 54
				],
				"select" => [
					"ID"
				]
			))['total'];
		}
		return $res;
	}

	public static function getReferralByCompanyId($idCompany,$contact = false) {
		if(!$contact) {
			$result = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID"=>22,
					"STAGE_ID"=>"C22:WON",
					"=COMPANY_ID" => $idCompany
				], //
				"select" => [
					"ID",
					"CATEGORY_ID",
					"COMPANY_ID", //REFERRAL ID COMPANY
					"CONTACT_ID", //REFERRAL ID CONTACT
					"UF_CRM_63DD4A344C9B9",
					"STAGE_ID"
				]
			))['result'];
		} else {
			$result = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID"=>22,
					"STAGE_ID"=>"C22:WON",
					"=CONTACT_ID" => $idCompany
				], //
				"select" => [
					"ID",
					"CATEGORY_ID",
					"COMPANY_ID", //REFERRAL ID COMPANY
					"CONTACT_ID", //REFERRAL ID CONTACT
					"UF_CRM_63DD4A344C9B9",
					"STAGE_ID"
				]
			))['result'];
		}
		return $result;
	}

	public static function getReferralListByINN($INN)
	{
		$result = \CRest ::call('crm.deal.list', array(
			"order" => ["ID"=>"ASC"],
			"filter" => ["CATEGORY_ID"=>22, "STAGE_ID"=>"C22:WON", "UF_CRM_63DD4A344C9B9"=>$INN], //
			"select" => [
				"ID",
				"CATEGORY_ID",
				"COMPANY_ID", //REFERRAL ID COMPANY
				"CONTACT_ID", //REFERRAL ID CONTACT
				"UF_CRM_63DD4A344C9B9",
				"STAGE_ID"
			]
		))['result'];

		return $result;
	}

	public static function get_current_leadSellerByID($leadID) {
		$resRef = \CRest::call('crm.lead.list', array(
			'order' => ['ID' => 'ASC'],
			'filter' => [
				"UTM_MEDIUM"=>"referral",
				"=ID" => $leadID
			],
			"select" => [
				"ID",
				"TITLE",
				"STAGE_ID",
				"STATUS_ID",
				"SOURCE_ID",
				"ASSIGNED_BY_ID",
				"COMPANY_ID", //REFERRAL ID COMPANY
				"CONTACT_ID", //REFERRAL ID CONTACT
				"UTM_CONTENT", //ИНН реферала
				"DATE_CREATE", //Дата лида
				"UTM_MEDIUM"
			]
		))['result'];

		$resSite = \CRest::call('crm.lead.list', array(
			'order' => ['ID' => 'ASC'],
			'filter' => [
				"SOURCE_ID"=>"UC_AY8XRH",
				"=ID" => $leadID
			],
			"select" => [
				"ID",
				"TITLE",
				"ASSIGNED_BY_ID",
				"STATUS_ID",
				"SOURCE_ID",
				"STAGE_ID",
				"COMPANY_ID", //REFERRAL ID COMPANY
				"CONTACT_ID", //REFERRAL ID CONTACT
				"UTM_CONTENT", //ИНН реферала
				"DATE_CREATE", //Дата лида
				"UTM_MEDIUM"
			]
		))['result'];

		$resWb = \CRest::call('crm.lead.list', array(
			'order' => ['ID' => 'ASC'],
			'filter' => [
				"SOURCE_ID"=>54,
				"=ID" => $leadID
			],
			"select" => [
				"ID",
				"TITLE",
				"ASSIGNED_BY_ID",
				"STATUS_ID",
				"SOURCE_ID",
				"STAGE_ID",
				"COMPANY_ID", //REFERRAL ID COMPANY
				"CONTACT_ID", //REFERRAL ID CONTACT
				"UTM_CONTENT", //ИНН реферала
				"DATE_CREATE", //Дата лида
				"UTM_MEDIUM"
			]
		))['result'];

		if(!empty($resRef))
		{
			$res = $resRef;
		} else {
			if (!empty($resWb))
			{
				$res = $resWb;
			} else {
				if (!empty($resSite))
				{
					$res = $resSite;
				} else {
					$res = [];
				}
			}
		}
		return $res;
	}

	public static function getSUMSellerByCompanyINN($params = array()) {
		$innReferral = $params['innReferral'];
		$wb = $params['wb'];
		if(!$wb){
			$resList = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID" => 23,
					"STAGE_ID" => "C23:WON",
					"UF_CRM_643163EE701AE" => $innReferral,
					"!==UF_CRM_1684985932" => null
				],
				"select" => [
					"ID",
					"TITLE",
					"UF_CRM_643163EE701AE",
					"LEAD_ID",
					"UF_CRM_1684985932"
				]
			))['result'];

		}else{
			$resList = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID" => 23,
					"STAGE_ID" => "C23:WON",
					"=SOURCE_ID" => 54,
					"!==UF_CRM_1684985932" => null
				],
				"select" => [
					"ID",
					"TITLE",
					"SOURCE_ID",
					"LEAD_ID",
					"UF_CRM_1684985932"
				]
			))['result'];
		}

		$result = array();
		$result['summaDogovora'] = 0;
		foreach($resList as $k => $el) {
			$result['summaDogovora'] += \CRest::call('crm.item.get', array("entityTypeId"=>188,"id" => $el['UF_CRM_1684985932']))['result']['item']['ufCrm15SsSummadogovora'];
		}
		return $result['summaDogovora'];
	}

	public static function getSUMSCPKBSellerByCompanyINN($params = array()) {
		$innReferral = $params['innReferral'];
		$scp_kb = $params['scp_kb'];
		$wb = $params['wb'];

		if(!$wb){
			$resList = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID" => 23,
					"STAGE_ID" => "C23:WON",
					"UF_CRM_643163EE701AE" => $innReferral,
					"!==UF_CRM_1684985932" => null
				],
				"select" => [
					"ID",
					"TITLE",
					"UF_CRM_643163EE701AE",
					"LEAD_ID",
					"UF_CRM_1684985932"
				]
			))['result'];

		}else{
			$resList = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID" => 23,
					"STAGE_ID" => "C23:WON",
					"=SOURCE_ID" => 54,
					"!==UF_CRM_1684985932" => null
				],
				"select" => [
					"ID",
					"TITLE",
					"SOURCE_ID",
					"LEAD_ID",
					"UF_CRM_1684985932"
				]
			))['result'];
		}
		$resSum = 0;
		$result = array();

		$resDiff = array('companyId'=>0,'contactId'=>0);
		foreach($resList as $k => $el) {
			$result[$k] = \CRest::call('crm.item.get', array("entityTypeId"=>188,"id" => $el['UF_CRM_1684985932']))['result']['item'];
		}
		foreach ($result as $l => $item)
		{
			if($item['companyId'] == $result[$l-1]['companyId']) {
				unset($result[$l]);
			} else {
				$resSum += $item['ufCrm15SsSummadogovora'];
			}
		}
		return $resSum * $scp_kb/100;

	}

	public static function getSellerDZbyID($id, $m = null) {
		if($m == null) {
			$res = \CRest::call('crm.item.get', array("entityTypeId"=>188, "id" => $id))['result'];
		} else {
			$dateFrom = date('Y-'.$m.'-01');
			$dateTo = date('Y-'.$m.'-31');

			$filter = [
				"id" => $id,
				">=ufCrm15_1679907577" => $dateFrom,
				"<=ufCrm15_1679907577" => $dateTo
			];
			$order = [
				"id" => "ASC"
			];
			$select = ["id","ufCrm15_1679907577","companyId","contactId"];

			$res = \CRest::call(
				'crm.item.list',
				array(
					"entityTypeId" => 188,
					$select,
					$order,
					$filter
				)
			)['result'];
		}

		return $res['item'];
	}

	public static function getLeads($INN){
		$resultLeadList__ = \CRest::call('crm.lead.list', array(
			"filter" =>["STATUS_ID"=>"CONVERTED", "UTM_MEDIUM" => "referral", "UTM_CONTENT" => $INN],
			"select" => ["STATUS_ID", "COMPANY_ID", "CONTACT_ID", "UTM_CONTENT", "DATE_CREATE"]
		));
		return $resultLeadList__['total'];
	}

	public static function setParamsForDealReferral($dealID){
		$params = [
			'order' => ['ID' => 'ASC'],
			'filter' => [
				"CATEGORY_ID"=>22,
				"STAGE_ID"=>"C22:WON",
				"!==UF_CRM_63DD4A344C9B9"=>null,
				">ID" => $dealID
			],
			"select" => [
				"ID",
				"COMPANY_ID", //REFERRAL ID COMPANY
				"CONTACT_ID", //REFERRAL ID CONTACT
				"UF_CRM_63DD4A344C9B9", //ИНН (SCP) актуальный
				"UF_CRM_63E0B06AF31D0", //Реферальная ссылка
				"UF_CRM_644267744B214", //SCP-KB
				"UF_CRM_4_QRL_PHOTO", //QR-Код
				"UF_CRM_63D8C56F039BA" //Правовая форма
			],
			'start' => -1
		];
		return $params;
	}

	public static function setParamsForLeadSeller($leadID, $order,$mounth)
	{

		if($mounth !== null) {
			$dateFrom = date('Y-'.$mounth.'-01');
			$dateTo = date('Y-'.$mounth.'-31');
			$params = [
				'order' => ['ID' => $order],
				'filter' => [
					">ID" => $leadID,
					"!=UF_CRM_1681147914" => "",
					">DATE_CREATE" => $dateFrom,
					"<DATE_CREATE" => $dateTo
				],
				"select" => [
					"ID",
					"TITLE",
					"STATUS_ID",
					"SOURCE_ID",
					"DATE_CREATE",
					"UF_CRM_1681147914"
				],
				'start' => -1
			];
		} else {
			$params = [
				'order' => ['ID' => $order],
				'filter' => [
					">ID" => $leadID
				],
				"select" => [
					"ID",
					"TITLE",
					"STATUS_ID",
					"SOURCE_ID",
					"DATE_CREATE",
					"UF_CRM_1681147914"
				],
				'start' => -1
			];
		}

		return $params;
	}

	public static function setParamsForDogovorZaim($DZID, $idReferral){
		$params = [
			'entityTypeId' => 188,
			'order' => ['ID' => 'ASC'],
			'filter' => [
				"stageId"=>"DT188_28:NEW",
				">id" => $DZID,
				[
					"logic" => "OR",
					["companyId" => $idReferral],
					["contactId" => $idReferral]
				]
			],
			"select" => ['*'],
			'start' => -1
		];
		return $params;
	}

	public static function get_all($method = "", $order="ASC", $mounth=null){
		$tokenID = '3lsxt0qfwbns0wve';
		$host = 'crm.seller-capital.ru';
		$user = 1;
		$finish = false;
		$ID = 0;
		$arResult = array();

		while (!$finish)
		{
			$http = new \Bitrix\Main\Web\HttpClient();
			$http->setTimeout(5);
			$http->setStreamTimeout(50);
			if($method == "crm.deal.list") {
				$params = self::setParamsForDealReferral($ID);
				$json = $http->post('https://'.$host.'/rest/'.$user.'/'.$tokenID.'/'.$method.'/',$params);
				$result = \Bitrix\Main\Web\Json::decode($json);
				$countResult = count($result['result']);
				if ($countResult > 0)
				{
					foreach ($result['result'] as $el)
					{
						$ID = $el['ID'];
					}
					$arResult = array_merge($arResult, $result['result']);
				}
				else
				{
					$finish = true;
				}
			}
			if($method == "crm.lead.list") {
				$params = self::setParamsForLeadSeller($ID,$order,$mounth);
				$json = $http->post('https://'.$host.'/rest/'.$user.'/'.$tokenID.'/'.$method.'/',$params);
				$result = \Bitrix\Main\Web\Json::decode($json);
				$countResult = count($result['result']);
				if ($countResult > 0)
				{
					foreach ($result['result'] as $el)
					{
						if ($el['STATUS_ID'] == "CONVERTED")
						{
							$ID = $el['ID'];
						}


					}
					$arResult = array_merge($arResult, $result['result']);
				}
				else
				{
					$finish = true;
				}
			}
		}
		return $arResult;
	}

	public static function getSellerByReferralINN($INN, $leadId, $wb = false) {
		//Сделка (SC) Оформление займа Селлерам
		//UF_CRM_1663045014 - Заявка
		//UF_CRM_1684985932 - Договор займа
		//UF_CRM_643163EE701AE - Данные о партнере реферале
		//SOURCE_ID -  $sourceId - Источник

		if(!$wb) {
			$res = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID" => 23,
					"UF_CRM_643163EE701AE" => $INN,
					"=LEAD_ID" => $leadId
				],
				"select" => [
					"ID",
					"LEAD_ID",
					"SOURCE_ID",
					"COMPANY_ID",
					"CONTACT_ID",
					"UTM_CONTENT",
					"UF_CRM_1663045014",
					"UF_CRM_1684985932",
					"UF_CRM_643163EE701AE"
				]
			))['result'][0];
		} else {
			$res = \CRest ::call('crm.deal.list', array(
				"order" => ["ID"=>"ASC"],
				"filter" => [
					"CATEGORY_ID" => 23,
					"SOURCE_ID" => 54,
					"=LEAD_ID" => $leadId
				],
				"select" => [
					"ID",
					"LEAD_ID",
					"SOURCE_ID",
					"COMPANY_ID",
					"CONTACT_ID",
					"UTM_CONTENT",
					"UF_CRM_1663045014",
					"UF_CRM_1684985932",
					"UF_CRM_643163EE701AE"
				]
			))['result'][0];
		}
		return $res;
	}

	// === Получить всех лидов по рефералу(*ИНН, *ВБ(true/false)) ===
	public static function getQTYLeadsByReferral($params = array()){
		$innReferral = $params['inn'];
		$wbReferral = $params['wb'];

		if(!$wbReferral) {
			$query = [
				'order' => ['ID' => 'ASC'],
				'filter' => [
					"UTM_MEDIUM" => "referral",
					"UTM_CONTENT" => $innReferral
				],
				"select" => [
					"ID",
					"STATUS_ID",
					"SOURCE_ID",
					"COMPANY_ID", //REFERRAL ID COMPANY
					"CONTACT_ID", //REFERRAL ID CONTACT
					"UTM_CONTENT", //ИНН реферала
					"DATE_CREATE", //Дата лида
					"UTM_MEDIUM"
				]
			];
		} else {
			$query = [
				'order' => ['ID' => 'ASC'],
				'filter' => [
					"SOURCE_ID" => 54
				],
				"select" => [
					"ID",
					"STATUS_ID",
					"SOURCE_ID",
					"COMPANY_ID", //REFERRAL ID COMPANY
					"CONTACT_ID", //REFERRAL ID CONTACT
					"UTM_CONTENT", //ИНН реферала
					"DATE_CREATE", //Дата лида
					"UTM_MEDIUM"
				]
			];
		}
		$resultLeadList__ = \CRest::call('crm.lead.list', $query);

		return $resultLeadList__['total'];

	}

	public static function getQTYDogovorZaimByReferralID($idReferral) {
		$seller = self::getListSellerByCompanyID($idReferral);

		$method = "crm.item.list";

		$tokenID = '5qy3faxohou1s7mr';
		$host = 'crm.seller-capital.ru';
		$user = 24554;
		$finish = false;
		$ID = 0;
		$arResult = array();

		while (!$finish)
		{
			$http = new \Bitrix\Main\Web\HttpClient();
			$http->setTimeout(5);
			$http->setStreamTimeout(50);
			if($method == "crm.item.list")
			{
				$params = [
					'entityTypeId' => 188,
					'order' => ['ID' => 'ASC'],
					'filter' => [
						"stageId"=>"DT188_28:NEW",
						">id" => $ID,
						[
							"logic" => "OR",
							["companyId" => $seller['COMPANY_ID']],
							["contactId" => $seller['CONTACT_ID']]
						]
					],
					"select" => ['*'],
					'start' => -1
				];
			}
			$json = $http->post('https://'.$host.'/rest/'.$user.'/'.$tokenID.'/'.$method.'/',$params);
			$result = \Bitrix\Main\Web\Json::decode($json);
			$countResult = count($result['result']['items']);
			if ($countResult > 0)
			{
				foreach ($result['result']['items'] as $el)
				{
					$ID = $el['id'];
				}
				$arResult = array_merge($arResult, $result['result']['items']);
			}
			else
			{
				$finish = true;
			}
		}
		return $arResult;
	}

	// endregion

}
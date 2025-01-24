<?
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
\Bitrix\Main\Loader::includeModule('crm');
$dealId = $_POST['deal_id'];
$phone = $_POST['phone'] ?? false;
$email = $_POST['email'] ?? false;
$result = [];
$result['success'] = 'n';
if (intval($dealId) > 0){
	$deal = CCrmDeal::GetById($dealId);
	if (isset($deal['CONTACT_ID']) && $deal['CONTACT_ID'] > 0){
		$contact = CCrmContact::GetByID($deal['CONTACT_ID']);
		if ($phone){
			$ct=new CCrmContact(false);
			$arParams = array('HAS_PHONE'=>'Y');
			$arParams['FM']['PHONE'] = array(
			   'n0' => array(
				'VALUE_TYPE' => 'WORK',
				'VALUE' => $phone
			   )
			  );
			$res = $ct->Update($deal['CONTACT_ID'],$arParams);
			$result['result'] = $res ? $phone . ' добавлен' : 'Некорректный номер' .$phone;
			$result['success'] = $res ? 'y' : 'n';
		}
		if ($email){
			$ct=new CCrmContact(false);
			$arParams = array('HAS_EMAIL'=>'Y');
			$arParams['FM']['EMAIL'] = array(
			   'n0' => array(
				'VALUE_TYPE' => 'WORK',
				'VALUE' => $email
			   )
			  );
			$res = $ct->Update($deal['CONTACT_ID'],$arParams);
			$result['result'] = $res ? $email . ' добавлен' : 'Некорректный email' . $email; 
			$result['success'] = $res ? 'y' : 'n';
		}
		echo json_encode($result);
	}

}
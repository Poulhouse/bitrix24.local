<?
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

define('STOP_STATISTICS', true);
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC',true);
define('NO_AGENT_CHECK', true);

$response = array();
$response['status'] = 'error';
$response['wrong_session'] = false;
$response['exec_time'] = microtime(true);
$response['errors'] = array();

//define('SITE_ID', $_POST['site_id']);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

Loc::loadMessages(__FILE__);
$l_prefix = 'WAS_CONDITION_FIELD_LIST_';

global $APPLICATION;
$APPLICATION->SetShowIncludeAreas(false);

if(isset($need_session_check) && $need_session_check){
  if(bitrix_sessid() != $_POST['session_id']){
    $response['errors'][] = GetMessage($l_prefix .'ERROR_SESSION');
    $response['wrong_session'] = true;
  }
}
$_POST = $APPLICATION->ConvertCharsetArray($_POST, 'UTF-8', SITE_CHARSET);
$response['session_id'] = bitrix_sessid();

if(isset($_POST['action'])){
  $response['status'] = 'ok';
  $action = trim($_POST['action']);
  if($action == 'get_list'){
    Loader::includeModule('crm');
    Loader::includeModule('iblock');
    
    $response['data'] = array();
    $response['data']['success'] = false;
    $response['data']['html'] = '';
    $condition = trim($_POST['field']);
    $value = trim($_POST['value']);
    $parts = explode('|', $condition);
    if(count($parts) == 2 && strlen($parts[1])){
      $type = $parts[0];
      $field_name = $parts[1];
      
      $entity_id = 'CRM_CONTACT';
      if($type == 'deal'){
        $entity_id = 'CRM_DEAL';
      }
      $arOrder = ['NAME' => 'ASC'];
      $arFilter = array(
        'ENTITY_ID' => $entity_id,
        'FIELD_NAME' => $field_name,
        'LANG' => 'ru',
      );
      $dbUFields = CUserTypeEntity::GetList($arOrder, $arFilter);
      $arUField = $dbUFields->Fetch();
      if($arUField){
        if($arUField['USER_TYPE_ID'] == 'enumeration'){
          $enum_values = array();
          $obEnum = new CUserFieldEnum;
          $arFilter = array(
            'USER_FIELD_ID' => $arUField['ID'],
          );
          $dbEnums = $obEnum->GetList(array(), $arFilter);
          while($arEnum = $dbEnums->GetNext()) {
            $enum_values[] = array(
              'NAME' => $arEnum['VALUE'],
              'VALUE' => $arEnum['ID'],
              'CHECKED' => $arEnum['ID'] == $value,
            );
          }
          $html = '';
          foreach($enum_values as $enum_value){
            $html .= '<option value="'. $enum_value['VALUE'] .'"'. ($enum_value['CHECKED'] ? ' selected' : '') .'>'. $enum_value['NAME'] .'</option>';
          }
          $response['data']['html'] = $html;
          $response['data']['success'] = true;
        }
      }
    }
  }
}

$response['exec_time'] = (microtime(true) - $response['exec_time']);
$response = $APPLICATION->ConvertCharsetArray($response, SITE_CHARSET, 'UTF-8');

header('Content-Type: application/json');
echo json_encode($response);

require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
?>
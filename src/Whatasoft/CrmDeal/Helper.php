<?
namespace Whatasoft\CrmDeal;

use Bitrix\Main\Type\DateTime as BDateTime;
use DateTime;

class Helper {
  public static function OnBeforeCrmDealAdd(&$arFields){
    $date = false;
    if(isset($arFields['UF_CRM_PLANNED_CALL'])){
      if(is_object($arFields['UF_CRM_PLANNED_CALL'])){
        $date = $arFields['UF_CRM_PLANNED_CALL'];
      }else{
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $arFields['UF_CRM_PLANNED_CALL']);
      }
    }
    if(!$date){
      $now = BDateTime::createFromPhp(new DateTime());
      $arFields['UF_CRM_PLANNED_CALL'] = $now;
    }
    
    if (isset($arFields[PROP_DEAL_CATEGORIES]) && !empty($arFields[PROP_DEAL_CATEGORIES])) {
      \Bitrix\Main\Loader::includeModule("iblock");
      \Bitrix\Main\Loader::includeModule("calendar");
      $calendarData = \CCalendar::GetSettings(array('getDefaultForEmpty' => true));
      $isNight = true;
      $curTime = date('H.i');
      if ($curTime > $calendarData['work_time_start'] && $curTime < $calendarData['work_time_end']){
        $isNight = false;
      }
      $filter = array("IBLOCK_ID" => QUEUE_LIST_IBLOCK_ID, "PROPERTY_CATEGORIES.ID" => $arFields[PROP_DEAL_CATEGORIES], "PROPERTY_IS_NIGHT" => $isNight ? 'Y' : 'N');
      $cRes = \CIBlockElement::GetList(array("PROPERTY_ORDER"), $filter);
      if($data = $cRes->GetNext()){
        $groupID = $data['ID'];
        $arFields[UF_CRM_QUEUE_GROUP] = $groupID;
      }
    }
    
    return true;
  }
  public static function OnAfterCrmDealAdd(&$arFields){

    return true;
  }

}
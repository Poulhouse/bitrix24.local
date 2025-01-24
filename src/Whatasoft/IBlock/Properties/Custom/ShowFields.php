<?
namespace Whatasoft\IBlock\Properties\Custom;

use CJSCore;

class ShowFields extends Base {
  public function __construct(){
    parent::__construct();
  }
  
  public function GetUserTypeDescription(){
    return [
      "PROPERTY_TYPE" => "S", 
      "USER_TYPE" => "SHOWFIELD",
      "DESCRIPTION" => "Поля сделки и контакта для вывода",
      "GetPropertyFieldHtml" => array(__CLASS__, "GetPropertyFieldHtml"),
      "GetPublicEditHTML" => array(__CLASS__, "GetPropertyFieldHtml"),
      "ConvertToDB" => array(__CLASS__, "ConvertToDB"),
      "ConvertFromDB" => array(__CLASS__, "ConvertFromDB"),
    ];
  }
  
  public static function GetPropertyFieldHtml(array $arProperty, $value, $strHTMLControlName){
    return (new self())->_getPropertyFieldHtml($arProperty, $value, $strHTMLControlName);
  }

  public static function ConvertToDB($arProperty, $arValue){
    if(isset($_POST['sort_field'][$arProperty['ID']])){
      $contact_fields = isset($_POST['sort_field'][$arProperty['ID']]['contact']) ? $_POST['sort_field'][$arProperty['ID']]['contact'] : array();
      $deal_fields = isset($_POST['sort_field'][$arProperty['ID']]['deal']) ? $_POST['sort_field'][$arProperty['ID']]['deal'] : array();
      $result = array(
        'contact' => $contact_fields,
        'deal' => $deal_fields,
      );
      $arValue['VALUE'] = json_encode($result);
    }else{
      $arValue['VALUE'] = '';
    }
    
    return $arValue;
  }
  
  public static function ConvertFromDB($arProperty, $arValue){
    if($arValue['VALUE']){
      $arValue['VALUE'] = json_decode($arValue['VALUE'], true);
    }
    
    return $arValue;
  }
  
  protected function _getPropertyFieldHtml(array $arProperty, $value, $strHTMLControlName){
    $handlerValueParam = (is_array($value)) ? $value['VALUE'] : [];
    $htmlStyles = $this->getPropertyFieldHtmlStyles();
    $htmlJS = $this->getPropertyFieldHtmlScripts();
    $crmContactPropertiesHtml = $this->getCrmContactPropertiesHtml($arProperty['ID'], $handlerValueParam);
    $crmDealPropertiesHtml = $this->getCrmDealPropertiesHtml($arProperty['ID'], $handlerValueParam);
    
    $header = '<div class="prop_field_deal_edit"><input type="hidden" name="'. $strHTMLControlName['VALUE'] .'" value="1">';
    $footer = "</div>";  
    $contact_header = "<div class='contact_fields'><div class='title'><strong>Поля контакта</strong><br /></div><div style='font-size:0.9em'><span style='width:45px;display:inline-block'>Сорт.</span><span>Название</span></div>";
    $contact_footer = "</div><br /><br />";
    $deal_header = "<div class='deal_fields'><div class='title'><strong>Поля сделки</strong><br /></div><div style='font-size:0.9em'><span style='width:45px;display:inline-block'>Сорт.</span><span>Название</span></div>";
    $deal_footer = "</div> <span style='color:rgb(40,40,40)'>Отметьте галочкой те поля, которые нужно выводить. Отсортируйте поля в нужном порядке.</span>";
    
    $htmlContentParts = [
      $htmlStyles,
      $htmlJS,
      $header,
      $contact_header,
      $crmContactPropertiesHtml,
      $contact_footer,
      $deal_header,
      $crmDealPropertiesHtml,
      $deal_footer,
      $footer,
    ];
    CJSCore::Init('jquery');
    
    return $this->glueHtmlContent($htmlContentParts);
  }
  
  protected function getPropertyFieldHtmlStyles(){
    ob_start();?>
    <style type="text/css">
      #workarea-content .bx-form-notes {display:none}
      .prop_field_deal_edit .up {
        transform: rotate(-135deg);
        -webkit-transform: rotate(-135deg);
      }
      .prop_field_deal_edit .down {
        transform: rotate(45deg);
        -webkit-transform: rotate(45deg);
      }
    </style>
    <?
    $content = ob_get_clean();
    return $content;
  }
  
  protected function getPropertyFieldHtmlScripts(){
    ob_start();?>
    <script type="text/javascript">
      $(document).ready(function(){
        
      })
      function swapField(a, needUp){
        var el = $(a).closest('.crm-field-wrapper');
        var prev;
        if (needUp)
          prev = $(el).prev();
        else
          prev = $(el).next();
        
        if(prev.hasClass('crm-field-wrapper')){
          if(needUp){
            prev.insertAfter(el);
          }else{
            prev.insertBefore(el);
          }
        }
        return false;
      }
    </script>
    <?
    $content = ob_get_clean();
    return $content;
  }
  
  protected function sortFields($a, $b){
    if($a['SORT'] == $b['SORT']){
      return 0;
    }
    return ($a['SORT'] < $b['SORT']) ? -1 : 1;
  }
  
  protected function getCrmContactPropertiesHtml($property_id, $value){
    $order = array();
    if(is_array($value) && isset($value['contact'])){
      $i = 1;
      foreach($value['contact'] as $val){
        $order[$val] = $i;
        $i++;
      }
    }
    $result_fields = array();
    $exclude_fields = $this->getFieldManager()->getCrmContactExcludeFields();
    
    $arCrmFields = $this->getFieldManager()->getCrmContactFields();
    foreach($arCrmFields as $field_name => $arField){
      if(in_array($field_name, $exclude_fields)){
        continue;
      }
      $field = array();
      $field['NAME'] = $arField['NAME'];
      $field['SORT'] = isset($order[$field_name]) ? $order[$field_name] : 500;
      $field['FORM_NAME'] = 'sort_field['. $property_id .'][contact][]';
      $field['VALUE'] = $field_name;
      $field['CHECKED'] = isset($order[$field_name]) ? true : false;
      $result_fields[] = $field;
    }
    
    $dbCrmProperties = $this->getFieldManager()->getCrmContactUserFields();
    while($arCrmProperty = $dbCrmProperties->Fetch()){
      $field_name = $arCrmProperty['FIELD_NAME'];
      if(in_array($field_name, $exclude_fields)){
        continue;
      }
      $field = array();
      $field['NAME'] = $arCrmProperty['EDIT_FORM_LABEL'];
      $field['SORT'] = isset($order[$field_name]) ? $order[$field_name] : 500;
      $field['FORM_NAME'] = 'sort_field['. $property_id .'][contact][]';
      $field['VALUE'] = $field_name;
      $field['CHECKED'] = isset($order[$field_name]) ? true : false;
      $result_fields[] = $field;
    }
    usort($result_fields, array($this, 'sortFields'));
    
    $html = '';
    foreach($result_fields as $field){
      $html .= $this->getCrmFieldHtml($field['VALUE'], $field['NAME'], $field['FORM_NAME'], $field['CHECKED']);
    }
    
    $appendix = 'Настроить список полей можно по <a target="_blank" href="/crm/configs/fields/CRM_CONTACT/">ссылке</a>';
    return $html . $appendix;
  }
  
  protected function getCrmDealPropertiesHtml($property_id, $value){
    $order = array();
    if(is_array($value) && isset($value['deal'])){
      $i = 1;
      foreach($value['deal'] as $val){
        $order[$val] = $i;
        $i++;
      }
    }
    $result_fields = array();
    $exclude_fields = $this->getFieldManager()->getCrmDealExcludeFields();
    
    $arCrmFields = $this->getFieldManager()->getCrmDealFields();
    foreach($arCrmFields as $field_name => $arField){
      if(in_array($field_name, $exclude_fields)){
        continue;
      }
      $field = array();
      $field['NAME'] = $arField['NAME'];
      $field['SORT'] = isset($order[$field_name]) ? $order[$field_name] : 500;
      $field['FORM_NAME'] = 'sort_field['. $property_id .'][deal][]';
      $field['VALUE'] = $field_name;
      $field['CHECKED'] = isset($order[$field_name]) ? true : false;
      $result_fields[] = $field;
    }
    
    $dbCrmProperties = $this->getFieldManager()->getCrmDealUserFields();
    while($arCrmProperty = $dbCrmProperties->Fetch()){
      $field_name = $arCrmProperty['FIELD_NAME'];
      if(in_array($field_name, $exclude_fields)){
        continue;
      }
      $field = array();
      $field['NAME'] = $arCrmProperty['EDIT_FORM_LABEL'];
      $field['SORT'] = isset($order[$field_name]) ? $order[$field_name] : 500;
      $field['FORM_NAME'] = 'sort_field['. $property_id .'][deal][]';
      $field['VALUE'] = $field_name;
      $field['CHECKED'] = isset($order[$field_name]) ? true : false;
      $result_fields[] = $field;
    }
    usort($result_fields, array($this, 'sortFields'));
    
    $html = '';
    foreach($result_fields as $field){
      $html .= $this->getCrmFieldHtml($field['VALUE'], $field['NAME'], $field['FORM_NAME'], $field['CHECKED']);
    }
    
    $appendix = 'Настроить список полей можно по <a target="_blank" href="/crm/configs/fields/CRM_DEAL/">ссылке</a>';
    return $html . $appendix;
  }
  
  protected function getCrmFieldHtml($value, $name, $form_name, $checked){
    ob_start();
    ?>
    <div style="margin:2px 0px 2px 0px;background-color:rgb(250,250,250)" class="crm-field-wrapper">
      <a href="#" onclick='return swapField(this, true);' style="font-weight:bold">&uarr;</a>
      <a href="#" onclick='return swapField(this, false);' style="font-weight:bold">&darr;</a>
      <label>
        <input type="checkbox" name="<?=$form_name?>" value="<?=$value?>" <?=($checked) ? 'checked' : ''?>/>
        <?=$name?> 
      </label> 
    </div>
    <?
    $content = ob_get_clean();
    return $content;
  }
  
  protected function getAllowedPropertyTypes(){
    return ["integer", "string", "datetime", "enumeration", "boolean"];
  }
  
  protected function glueHtmlContent(array $htmlContentParts){
    return implode("", array_map("trim", $htmlContentParts));
  }
}
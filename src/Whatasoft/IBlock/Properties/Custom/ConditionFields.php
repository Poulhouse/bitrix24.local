<?
namespace Whatasoft\IBlock\Properties\Custom;

use CJSCore;

class ConditionFields extends Base {
  protected $condition_fields = null;
  public function __construct(){
    parent::__construct();
  }
  
  public function GetUserTypeDescription(){
    return [
      "PROPERTY_TYPE" => "S", 
      "USER_TYPE" => "CONDITIONFIELD",
      "DESCRIPTION" => "Поля сделки и контакта для вывода (по условию)",
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
    if(isset($_POST['cond_field'][$arProperty['ID']])){
      $result = array();
      $types = array('deal', 'contact');
      foreach($types as $type){
        $order = isset($_POST['cond_field'][$arProperty['ID']][$type]['order']) ? $_POST['cond_field'][$arProperty['ID']][$type]['order'] : array();
        $condition = isset($_POST['cond_field'][$arProperty['ID']][$type]['condition']) ? $_POST['cond_field'][$arProperty['ID']][$type]['condition'] : array();
        $condition_value = isset($_POST['cond_field'][$arProperty['ID']][$type]['condition_value']) ? $_POST['cond_field'][$arProperty['ID']][$type]['condition_value'] : array();
        $i = 1;
        $type_fields = array();
        foreach($order as $field_name){
          $item = array();
          $item['SORT'] = $i;
          if(isset($condition[$field_name]) && strlen($condition[$field_name])){
            $item['COND'] = $condition[$field_name];
            $item['VAL'] = $condition_value[$field_name];
          }
          $type_fields[$field_name] = $item;
          $i++;
        }
        $result[$type] = $type_fields;
      }
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
    $crmContactPropertiesHtml = $this->getCrmPropertiesHtml('contact', $arProperty['ID'], $handlerValueParam);
    $crmDealPropertiesHtml = $this->getCrmPropertiesHtml('deal', $arProperty['ID'], $handlerValueParam);
    
    $header = '<div class="prop_field_deal_edit"><input type="hidden" name="'. $strHTMLControlName['VALUE'] .'" value="1">';
    $footer = "</div>";  
    $contact_header = "<div class='contact_fields custom-fields'><div class='title'><strong>Поля контакта</strong><br /></div><div style='font-size:0.9em'><span style='width:45px;display:inline-block'>Сорт.</span><span>Название</span></div>";
    $contact_footer = "</div><br /><br />";
    $deal_header = "<div class='deal_fields custom-fields'><div class='title'><strong>Поля сделки</strong><br /></div><div style='font-size:0.9em'><span style='width:45px;display:inline-block'>Сорт.</span><span>Название</span></div>";
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
      .custom-fields .crm-field-wrapper {
        display: -webkit-flex;
        display: -moz-flex;
        display: -ms-flex;
        display: -o-flex;
        display: flex;
        -webkit-align-items: center;
        align-items: center; }
        .custom-fields .crm-field-wrapper * {
          box-sizing: border-box; }
        .custom-fields .crm-field-wrapper .field {
          flex: 1 1 50px;
          width: 30%;
          max-width: 250px; }
        .custom-fields .crm-field-wrapper .dependencies {
          background-color: #FCF6DC;
          flex: 1 1 100px;
          padding: 5px 10px 5px 3%;
          display: -webkit-flex;
          display: -moz-flex;
          display: -ms-flex;
          display: -o-flex;
          display: flex;
          align-items: center; }
          .custom-fields .crm-field-wrapper .dependencies .select-holder {
            max-width: 225px;
            flex: 1 1 100px;
            margin: 0 7px; }
          .custom-fields .crm-field-wrapper .dependencies select {
            width: 100%;
            white-space: nowrap; }
          .custom-fields .crm-field-wrapper .dependencies .general-select {
            max-width: 380px;
            width: 60%;
            display: -webkit-flex;
            display: -moz-flex;
            display: -ms-flex;
            display: -o-flex;
            display: flex;
            align-items: center; }
          .custom-fields .crm-field-wrapper .dependencies .value-of {
            display: -webkit-flex;
            display: -moz-flex;
            display: -ms-flex;
            display: -o-flex;
            display: flex;
            align-items: center;
            -webkit-align-items: center;
            align-items: center;
            width: 40%;
            max-width: 291px; }
            .custom-fields .crm-field-wrapper .dependencies .value-of:not(.show) > * {
              visibility: hidden; }
        @media (max-width: 1299px) {
          .custom-fields .crm-field-wrapper .dependencies {
            display: block; }
            .custom-fields .crm-field-wrapper .dependencies .general-select {
              width: auto;
              max-width: none; }
            .custom-fields .crm-field-wrapper .dependencies .value-of {
              max-width: none;
              width: auto; }
              .custom-fields .crm-field-wrapper .dependencies .value-of:not(.show) > * {
                display: none; }
              .custom-fields .crm-field-wrapper .dependencies .value-of span {
                width: 105px;
                text-align: right; }
              .custom-fields .crm-field-wrapper .dependencies .value-of.show {
                margin-top: 5px; } }
      
      .custom-select {
        padding: 4px 1.75rem 4px 0.75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #495057;
        vertical-align: middle;
        background: #fff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='4' height='5' viewBox='0 0 4 5'%3e%3cpath fill='%23343a40' d='M2 0L0 2h4zm0 5L0 3h4z'/%3e%3c/svg%3e") no-repeat right 0.75rem center/8px 10px;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
      }
    </style>
    <?
    $content = ob_get_clean();
    return $content;
  }
  
  protected function getPropertyFieldHtmlScripts(){
    ob_start();?>
    <script type="text/javascript">
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
      
      function GetFieldDependencyList(_field, _value){
        return new Promise(function(resolve, reject){
          var errors = [];
          
          _value = typeof _value !== 'undefined' ? _value : '';
          
          var data = {
            action: 'get_list',
            field: _field,
            value: _value,
            session_id: BX.bitrix_sessid()
          };
          
          var settings = {
            url: "/bitrix/admin/whatasoft_condition_field_list.php",
            type: "POST",
            cache: false,
            dataType: "json",
            data: data
          };
          
          var request = $.ajax(settings);
          request.done(function(data){
            BX.message['bitrix_sessid'] = data.session_id;
            if(data.status == "ok"){
              if(data.data.success){
                resolve(data.data);
              }else{
                errors.push(data.data.message);
                reject(errors);
              }
            }else{
              if(data.wrong_session){
                errors.push("Wrong session");
                reject(errors);
              }
            }
          });
          
          request.fail(function(jqXHR, textStatus){
            errors.push("Request failed: " + textStatus);
            reject(errors);
          });
        });
      }
      
      $(document).ready(function(){
        var dependencies = $('.dependencies');
        $('.select_condition', dependencies).on('change', function(){
          var block = $(this).closest('.dependencies');
          var val = $(this).val();
          if(val.length){
            $('.value-of', block).addClass('show');
            GetFieldDependencyList(val).then(function(data){
              $('.value-of select', block).html(data.html);
            }).catch(function(errors){
              console.log(errors);
            });
          }else{
            $('.value-of', block).removeClass('show');
          }
        });
        $('.select_condition', dependencies).each(function(index){
          var block = $(this).closest('.dependencies');
          var field = $(this).val();
          var value = $(this).data('value');
          if(field.length){
            $('.value-of', block).addClass('show');
            GetFieldDependencyList(field, value).then(function(data){
              $('.value-of select', block).html(data.html);
            }).catch(function(errors){
              console.log(errors);
            });
          }
        });
      });
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
  
  protected function getCrmPropertiesHtml($_type, $_property_id, $_value){
    $value = array();
    if(is_array($_value) && isset($_value[$_type])){
      $value = $_value[$_type];
    }
    $result_fields = array();
    $exclude_fields = $this->getFieldManager()->getCrmExcludeFields($_type);
    
    $arCrmFields = $this->getFieldManager()->getCrmFields($_type);
    foreach($arCrmFields as $field_name => $arField){
      if(in_array($field_name, $exclude_fields)){
        continue;
      }
      $field = array();
      $field['NAME'] = $arField['NAME'];
      $field['VALUE'] = $field_name;
      $result_fields[] = $field;
    }
    
    $dbCrmProperties = $this->getFieldManager()->getCrmUserFields($_type);
    while($arCrmProperty = $dbCrmProperties->Fetch()){
      $field_name = $arCrmProperty['FIELD_NAME'];
      if(in_array($field_name, $exclude_fields)){
        continue;
      }
      $field = array();
      $field['NAME'] = $arCrmProperty['EDIT_FORM_LABEL'];
      $field['VALUE'] = $field_name;
      $result_fields[] = $field;
    }
    
    foreach($result_fields as &$field){
      $field['SORT'] = isset($value[$field['VALUE']]) ? $value[$field['VALUE']]['SORT'] : 500;
      $field['CHECKED'] = isset($value[$field['VALUE']]) ? true : false;
      $field['CONDITION'] = isset($value[$field['VALUE']]) ? $value[$field['VALUE']]['COND'] : '';
      $field['CONDITION_VALUE'] = isset($value[$field['VALUE']]) ? $value[$field['VALUE']]['VAL'] : '';
      $field['FORM_SORT'] = 'cond_field['. $_property_id .']['. $_type .'][order][]';
      $field['FORM_CONDITION'] = 'cond_field['. $_property_id .']['. $_type .'][condition]['. $field['VALUE'] .']';
      $field['FORM_CONDITION_VALUE'] = 'cond_field['. $_property_id .']['. $_type .'][condition_value]['. $field['VALUE'] .']';
      $field['CONDITIONS'] = $this->getConditions($_type .'|'. $field['VALUE'], $field['CONDITION'], $field['CONDITION_VALUE']);
    }
    unset($field);
    usort($result_fields, array($this, 'sortFields'));
    
    $html = '';
    foreach($result_fields as $field){
      $html .= $this->getCrmFieldHtml($field);
    }
    
    if($_type == 'deal'){
      $url_part = 'CRM_DEAL';
    }else{
      $url_part = 'CRM_CONTACT';
    }
    
    $appendix = 'Настроить список полей можно по <a target="_blank" href="/crm/configs/fields/'. $url_part .'/">ссылке</a>';
    return $html . $appendix;
  }
  
  protected function getCrmFieldHtml($_field){
    ob_start();
    ?>
    <div style="margin:2px 0px 2px 0px;background-color:rgb(250,250,250)" class="crm-field-wrapper">
      <div class="field">
        <a href="#" onclick='return swapField(this, true);' style="font-weight:bold">&uarr;</a>
        <a href="#" onclick='return swapField(this, false);' style="font-weight:bold">&darr;</a>
        <label>
          <input type="checkbox" name="<?=$_field['FORM_SORT']?>" value="<?=$_field['VALUE']?>"<?=($_field['CHECKED'] ? ' checked' : '')?>/>
          <?=$_field['NAME']?>
        </label>
      </div>
      <div class="dependencies">
        <div class="general-select">
          <b>Условие показа:</b>
          <div class="select-holder">
            <select name="<?=$_field['FORM_CONDITION']?>" class="custom-select select_condition" data-value="<?=$_field['CONDITION_VALUE']?>">
              <?foreach($_field['CONDITIONS'] as $condition){?>
              <option value="<?=$condition['VALUE']?>"<?=($condition['CHECKED'] ? ' selected' : '')?>><?=$condition['NAME']?></option>
              <?}?>
            </select>
          </div>
        </div>
        <div class="value-of">
          <span>значение</span>
          <div class="select-holder">
            <select name="<?=$_field['FORM_CONDITION_VALUE']?>" class="custom-select select_val"></select>
          </div>
        </div>
      </div>
    </div>
    <?
    $content = ob_get_clean();
    return $content;
  }
  
  protected function getConditions($_field_name, $_condition, $_value){
    $fields = $this->getAllConditionFields();
    $fields = array_diff_key($fields, array($_field_name => 1));
    
    foreach($fields as &$field){
      $field['CHECKED'] = false;
      $field['DEPENDENCY_VALUE'] = '';
      if($field['VALUE'] == $_condition){
        $field['CHECKED'] = true;
        $field['DEPENDENCY_VALUE'] = $_value;
      }
    }
    unset($field);
    
    return $fields;
  }
  
  protected function getAllConditionFields(){
    if($this->condition_fields == null){
      $this->condition_fields = array();
      
      $field = array();
      $field['NAME'] = 'Всегда';
      $field['CODE'] = '';
      $field['VALUE'] = '';
      $field['USER_TYPE_ID'] = '';
      $this->condition_fields['empty'] = $field;
      
      $types = array('deal', 'contact');
      foreach($types as $type){
        $exclude_fields = $this->getFieldManager()->getCrmExcludeFields($type);
        $dbCrmProperties = $this->getFieldManager()->getCrmUserFields($type);
        while($arCrmProperty = $dbCrmProperties->Fetch()){
          $field_name = $arCrmProperty['FIELD_NAME'];
          if(in_array($field_name, $exclude_fields)){
            continue;
          }
          $field = array();
          $field['NAME'] = '['. $type .'] '. $arCrmProperty['EDIT_FORM_LABEL'];
          $field['CODE'] = $field_name;
          $field['VALUE'] = $type .'|'. $field['CODE'];
          $field['USER_TYPE_ID'] = $arCrmProperty['USER_TYPE_ID'];
          if($arCrmProperty['USER_TYPE_ID'] == 'enumeration'){
            $this->condition_fields[$field['VALUE']] = $field;
          }
        }
      }
    }
    
    return $this->condition_fields;
  }
  
  protected function getAllowedPropertyTypes(){
    return ["integer", "string", "datetime", "enumeration", "boolean"];
  }
  
  protected function glueHtmlContent(array $htmlContentParts){
    return implode("", array_map("trim", $htmlContentParts));
  }
}
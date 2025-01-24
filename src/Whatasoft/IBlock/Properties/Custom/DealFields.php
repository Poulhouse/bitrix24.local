<?php

namespace Whatasoft\IBlock\Properties\Custom;

use Bitrix\Main\Loader; 

Loader::includeModule("highloadblock"); 

use Bitrix\Highloadblock as HL; 
use Bitrix\Main\Entity;

class DealFields extends Base
{
    public function __construct() 
    {
        parent::__construct();
    }
    
    public function GetUserTypeDescription()
    {
        return [
            "PROPERTY_TYPE"        => "S", 
            "USER_TYPE"            => "DEALFIELD",
            "DESCRIPTION"          => "Связь с полями сделки и клиента",
            "GetPropertyFieldHtml" => array(__CLASS__, "GetPropertyFieldHtml"),
            "GetPublicEditHTML" => array(__CLASS__, "GetPropertyFieldHtml"),
            "ConvertToDB" => array(__CLASS__, "ConvertToDB"),
            "ConvertFromDB" => array(__CLASS__, "ConvertFromDB"),
        ];
    }
    
    public static function GetPropertyFieldHtml(array $arProperty, $value, $strHTMLControlName)
    {
        return (new self())->_getPropertyFieldHtml($arProperty, $value, $strHTMLControlName);
    }
    
    public static function GetPropertyHtml($arProperty, $value, $strHTMLControlName)
    {
        $ret = '<div class="adm-input-wrap adm-input-wrap-calendar"><input class="adm-input adm-input-calendar" type="text" name="'.$strHTMLControlName["VALUE"].'" size="23" value="'.$value['VALUE'].'">';
        $ret .= '<span class="adm-calendar-icon" title="Нажмите для выбора даты" onclick="BX.calendar({node:this, field:\''.$strHTMLControlName["VALUE"].'\', form: \'\', bTime: true, bHideTime: false});"></span>';
        $ret .= '</div>';
        return $ret;
    }

    public static function ConvertToDB($arProperty, $arValue)
    {
		if (isset($_POST['order'][$arProperty['ID']])){
			$hlbl = 4; 
			$hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch(); 
			$entity = HL\HighloadBlockTable::compileEntity($hlblock); 
			$entity_data_class = $entity->getDataClass(); 
			$rsData = $entity_data_class::getList(array(
			   "select" => array("*"),
			   "order" => array("ID" => "ASC"),
			   "filter" => array("UF_PROPERTY_ID"=>$arProperty['ID'] . '-' . $arProperty['ELEMENT_ID']) 
			));
			$currentDataID = 0;

			if($arData = $rsData->Fetch()){
				$currentDataID = $arData['ID'];
			}
			 $data = array(
				  "UF_PROPERTY_ID"=>$arProperty['ID'] . '-' . $arProperty['ELEMENT_ID'],
				  "UF_ORDER_VALUE"=> serialize($_POST['order'][$arProperty['ID']])
			   );
			if ($currentDataID > 0){
				$entity_data_class::update($currentDataID, $data);
			} else {
				$result = $entity_data_class::add($data);
			}
		}
        $arValue['VALUE'] = serialize($arValue['VALUE']);
        return $arValue;
    }
    
    public static function ConvertFromDB($arProperty, $arValue)
    {
        if ($arValue['VALUE']) {
            $arValue['VALUE'] = unserialize($arValue['VALUE']);
        }

        return $arValue;
    }
    protected $arCurOrder = [];
	protected $curPropId = 0;
	protected function _getPropertyOrders($propertyId, $elementId){
		$this->curPropId = $propertyId;
		$hlbl = 4; 
		$hlblock = HL\HighloadBlockTable::getById($hlbl)->fetch(); 
		$entity = HL\HighloadBlockTable::compileEntity($hlblock); 
		$entity_data_class = $entity->getDataClass(); 
		$rsData = $entity_data_class::getList(array(
		   "select" => array("*"),
		   "order" => array("ID" => "ASC"),
		   "filter" => array("UF_PROPERTY_ID"=>$propertyId . '-' . $elementId) 
		));
		$currentDataID = 0;
		$arValue = array();
		if($arData = $rsData->Fetch()){
			$result = unserialize($arData['UF_ORDER_VALUE']);
			foreach($result as $name=>$value){

				$cName = str_replace(array('lsqb;', 'rsqb;'), array('[', ']'), $name);
				$arValue[$cName] = $value;
			}
			$this->arCurOrder = $arValue;
		}
	}
    protected function _getPropertyFieldHtml(array $arProperty, $value, $strHTMLControlName)
    {
		$this->_getPropertyOrders($arProperty['ID'], $arProperty['ELEMENT_ID']);
        $handlerValueParam = (is_array($value)) ? $value : [];
        $htmlStyles = $this->getPropertyFieldHtmlStyles();
        $crmContactPropertiesHtml = $this->getCrmContactPropertiesHtml($handlerValueParam, $strHTMLControlName);
        $crmDealPropertiesHtml = $this->getCrmDealPropertiesHtml($handlerValueParam, $strHTMLControlName);

		ob_start();
		?>
		<script type="text/javascript">
			$().ready(function(){

				$('.contact_fields').each(function(i, contact_fields){
					$(contact_fields).find('.crm-field-wrapper').each(function(j, el){
						$(contact_fields).find('.crm-field-wrapper').each(function(k, el2){
							var orderEl = parseInt($(el).data('order'));
							var orderEl2 = parseInt($(el2).data('order'));
							if (orderEl > orderEl2){
								$(el2).insertBefore($(el));
							}
						})
					})
				})
				$('.contact_fields').each(function(i, contact_fields){
					$(contact_fields).find('.crm-field-wrapper').each(function(j, el){
						$(el).data('order', (j+1)).find('.input-order-value').val((j+1));
					})
				})


				$('.deal_fields').each(function(i, contact_fields){
					$(contact_fields).find('.crm-field-wrapper').each(function(j, el){
						$(contact_fields).find('.crm-field-wrapper').each(function(k, el2){
							var orderEl = parseInt($(el).data('order'));
							var orderEl2 = parseInt($(el2).data('order'));
							if (orderEl > orderEl2){
								$(el2).insertBefore($(el));
							}
						})
					})
				})
				$('.deal_fields').each(function(i, contact_fields){
					$(contact_fields).find('.crm-field-wrapper').each(function(j, el){
						$(el).data('order', (j+1)).find('.input-order-value').val((j+1));
					})
				})
			})
			function swapFieldUpDown(a, needUp){
				var el = $(a).closest('.crm-field-wrapper');
				var prev;
				if (needUp)
					prev = $(el).prev();
				else
					prev = $(el).next();

				console.log(el);
				console.log(prev); 
				if (prev.hasClass('crm-field-wrapper')){
					var order = $(el).data('order');
					var prevOrder = prev.data('order');
					$(el).data('order', prevOrder);
					prev.data('order', order);
					$(el).find('.input-order-value').val(prevOrder);
					$(prev).find('.input-order-value').val(order);
					if (needUp){
						prev.insertAfter(el);
					} else {
						prev.insertBefore(el);
					}
				}
				return false;
			}
		</script>
		<?
		$dditionalScripts = ob_get_clean();
        $header = $dditionalScripts. "<div class='prop_field_deal_edit'>";
		$footer = "</div><style>#workarea-content .bx-form-notes{display:none}</style>";
        
		$contact_header = "<div class='contact_fields'><div class='title'><strong>Поля контакта</strong><br /></div><div style='font-size:0.9em'><span style='width:45px;display:inline-block'>Сорт.</span><span>Название</span></div>";
        $contact_footer = 'Настроить список полей можно по <a target="_blank" href="/crm/configs/fields/CRM_CONTACT/">ссылке</a>' . "</div><br /><br />";

		$deal_header = "<div class='deal_fields' style=''><div class='title'><strong>Поля сделки</strong><br /></div><div style='font-size:0.9em'><span style='width:45px;display:inline-block'>Сорт.</span><span>Название</span></div>";
		$deal_footer = "</div> <span style='color:rgb(40,40,40)'>Отметьте галочкой те поля, которые нужно выводить. Заполните поле 'Сорт.' для определения порядка показа поля. Чем меньше значение, тем раньше будет показано свойство.</span>";
        
        $htmlContentParts = [
            $htmlStyles,
            $header,
            $contact_header,
            $crmContactPropertiesHtml,
            $contact_footer,
            $deal_header,
            $crmDealPropertiesHtml,
            $deal_footer,
            $footer
        ];
        
        return $this->glueHtmlContent($htmlContentParts);
    }
    
    protected function getPropertyFieldHtmlStyles()
    {
        ob_start();?>
        <style type="text/css">
        .prop_field_deal_edit .up {
            transform: rotate(-135deg);
            -webkit-transform: rotate(-135deg);
        }
        .prop_field_deal_edit .down {
            transform: rotate(45deg);
            -webkit-transform: rotate(45deg);
        }
        .prop_field_deal_edit i {
            border: solid black;
            border-width: 0 1px 1px 0;
            display: inline-block;
            padding: 3px;
        }
        </style>
        <?
        $content = ob_get_clean();
        return $content;
    }
    
    protected function getCrmContactPropertiesHtml($handlerValue, $handlerStrHTMLControlName)
    {
        $html = '';
        // Выбранные поля контакта (включая пользовательские свойства)
        $arCheckedContactFieldNames = $this->getCheckedContactFieldsFromHandlerValueParam($handlerValue);
        // Все основные поля контакта
        $arCrmContactFields = $this->getFieldManager()->getCrmContactFields();
        // Все пользовательские свойства контакта
        $rsCrmContactProperties = $this->getFieldManager()->getCrmContactUserFields();
        foreach ($arCrmContactFields as $fieldName => $arField) {
            $fieldCapiton = (string)$this->getFieldManager()->getCrmContactFieldCapiton($fieldName);
            $fieldFormName = (string)$this->getCrmContactFieldFormName($handlerStrHTMLControlName);
            $fieldType = (string)$arField['TYPE'];
            $isFieldChecked = (bool)$this->isFieldChecked($fieldName, $arCheckedContactFieldNames);
            
            if ($this->isPropertyShowAllowed($fieldType, $fieldCapiton)) {
                $html .= $this->getCrmFieldHtml($fieldName, $fieldCapiton, $fieldFormName, $isFieldChecked);
            }
        }
        
        while ($arCrmContactProperty = $rsCrmContactProperties->Fetch()) {
            $fieldName = (string)$arCrmContactProperty['FIELD_NAME'];
            $fieldType = (string)$arCrmContactProperty['USER_TYPE_ID'];
            $fieldCapiton = (string)$arCrmContactProperty['EDIT_FORM_LABEL'];
            $fieldFormName = (string)$this->getCrmContactFieldFormName($handlerStrHTMLControlName);
            $isFieldChecked = (bool)$this->isFieldChecked($fieldName, $arCheckedContactFieldNames);
            
            if ($this->isPropertyShowAllowed($fieldType, $fieldCapiton)) {
                $html .= $this->getCrmFieldHtml($fieldName, $fieldCapiton, $fieldFormName, $isFieldChecked);
            }
        }
        
        return $html;
    }
    
    protected function getCrmContactFieldFormName($handlerStrHTMLControlName)
    {
        return $handlerStrHTMLControlName['VALUE'] . "[CONTACT][]";
    }
    
    protected function getCrmDealPropertiesHtml($handlerValue, $handlerStrHTMLControlName)
    {
        $html = '';
        // Выбранные поля сделки (включая пользовательские поля)
        $arCheckedDealFieldNames = $this->getCheckedDealFieldsFromHandlerValueParam($handlerValue);
        // Все основные поля сделки
        $arCrmDealFields = $this->getFieldManager()->getCrmDealFields();
        foreach ($arCrmDealFields as $fieldName => $arField) {
            $fieldCapiton = (string)$this->getFieldManager()->getCrmDealFieldCapiton($fieldName);
            $fieldFormName = (string)$this->getCrmDealFieldFormName($handlerStrHTMLControlName);
            $fieldType = (string)$arField['TYPE'];
            $isFieldChecked = (bool)$this->isFieldChecked($fieldName, $arCheckedDealFieldNames);
            
            if ($this->isPropertyShowAllowed($fieldType, $fieldCapiton)) {
                $html .= $this->getCrmFieldHtml($fieldName, $fieldCapiton, $fieldFormName, $isFieldChecked);
            }
        }
        
        $exclude_fields = array(
          'UF_CRM_PLANNED_CALL',
          'UF_CRM_QUEUE_GROUP',
          'UF_CRM_CITY',
          'UF_CRM_LOCKED_BY',
          'UF_CRM_LOCKED_FROM',
          'UF_CRM_STATUS',
		 'UF_CRM_DECLINE',
          'UF_CRM_MEET_OFFICE',
          'UF_CRM_MEET_DATE',
          'UF_CRM_CALL_DATE',
          'UF_CRM_CALL_RETRIES',
        );
        
        // Все пользовательские свойства сделки
        $rsCrmDealProperties = $this->getFieldManager()->getCrmDealUserFields();
        while($arCrmDealProperty = $rsCrmDealProperties->Fetch()){
          $fieldName = (string)$arCrmDealProperty['FIELD_NAME'];
          $fieldType = (string)$arCrmDealProperty['USER_TYPE_ID'];
          $fieldCapiton = (string)$arCrmDealProperty['EDIT_FORM_LABEL'];
          $fieldFormName = (string)$this->getCrmDealFieldFormName($handlerStrHTMLControlName);
          $isFieldChecked = (bool)$this->isFieldChecked($fieldName, $arCheckedDealFieldNames);
          
          if(in_array($fieldName, $exclude_fields)){
            continue;
          }

          $html .= $this->getCrmFieldHtml($fieldName, $fieldCapiton, $fieldFormName, $isFieldChecked);
          //if($this->isPropertyShowAllowed($fieldType, $fieldCapiton)) {
          //}
        }
        $appendix = 'Настроить список полей можно по <a target="_blank" href="/crm/configs/fields/CRM_DEAL/">ссылке</a>';
        return $html . $appendix;
    }
    
    protected function getCrmDealFieldFormName($handlerStrHTMLControlName)
    {
        return $handlerStrHTMLControlName['VALUE'] . "[DEAL][]";
    }
    
    protected function getCrmFieldHtml(
        string $fieldName,
        string $fieldCapiton,
        string $fieldFormName,
        bool $isChecked
    )
    {
        ob_start();
        ?>
            <!--<a href="#"><i class="arrow up"></i></a>&nbsp;&nbsp;<a href="#"><i class="arrow down"></i></a>-->

			<?
				$tmpFieldFormName = str_replace(array('[', ']'), array('lsqb;', 'rsqb;'), $fieldFormName);
				$curOrder = isset($this->arCurOrder[$fieldFormName . '-' . $fieldName]) ? $this->arCurOrder[$fieldFormName . '-' . $fieldName] : 500;
			?>
			<div style="margin:2px 0px 2px 0px;background-color:rgb(250,250,250)" class="crm-field-wrapper" data-order="<?=$curOrder?>" data-prop-id="<?=$this->curPropId?>">
				<a href="#" onclick='return swapFieldUpDown(this, true);' style="font-weight:bold">&uarr;</a> <a href="#" onclick='return swapFieldUpDown(this, false);' style="font-weight:bold">&darr;</a><input type="hidden" style="width: 30px;" class="input-order-value" value="<?=$curOrder?>" name="order[<?=$this->curPropId?>][<?=$tmpFieldFormName?>-<?=$fieldName?>]" />
			<label>
				<input type="checkbox" <?=($isChecked) ? 'checked' : ''?> 
					   name="<?=$fieldFormName?>"
					   value="<?=$fieldName?>" />
				<?=$fieldCapiton?> 
			</label> 
			</div>

        <?
        $content = ob_get_clean();
        return $content;
    }
    
    protected function getCheckedContactFieldsFromHandlerValueParam(array $value)
    {
        return $value["VALUE"]["CONTACT"] ?? [];
    }
    
    protected function getCheckedDealFieldsFromHandlerValueParam(array $value)
    {
        return $value["VALUE"]["DEAL"] ?? [];
    }
    
    protected function getAllowedPropertyTypes()
    {
        return ["integer", "string", "datetime", "enumeration", "boolean"];
    }
    
    protected function isFieldChecked(string $fieldName, array $checkedFieldNames)
    {
        return in_array($fieldName, $checkedFieldNames);
    }
    
    protected function isPropertyShowAllowed(string $propType, string $propCapiton)
    {
        return $this->isPropertyTypeAllowed($propType) && $this->isPropertyCapitonAllowed($propCapiton);
    }

    protected function isPropertyTypeAllowed(string $propType)
    {
        return in_array($propType, $this->getAllowedPropertyTypes());
    }
    
    protected function isPropertyCapitonAllowed(string $propCapiton)
    {
        return strlen($propCapiton) > 0;
    }
    
    protected function glueHtmlContent(array $htmlContentParts)
    {
        return implode("", array_map("trim", $htmlContentParts));
    }
}
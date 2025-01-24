<?php

namespace Whatasoft\IBlock\Properties\Custom;

use Bitrix\Main\Loader; 
use Bitrix\Main\Entity;

class OrderedIBlockSectionFields extends Base
{
	private const SOURCE_IBLOCK_ID = 44;
    public function __construct() 
    {
        parent::__construct();
    }
    
    public function GetUserTypeDescription()
    {
        return [
            "PROPERTY_TYPE"        => "S", 
            "USER_TYPE"            => "ORDEREDSECTIONFIELD",
            "DESCRIPTION"          => "Упорядоченная связь с разделами",
            "GetPropertyFieldHtml" => array(__CLASS__, "GetPropertyFieldHtml"),
            "GetPublicEditHTML" => array(__CLASS__, "GetPropertyFieldHtml"),
            "ConvertToDB" => array(__CLASS__, "ConvertToDB"),
            "ConvertFromDB" => array(__CLASS__, "ConvertFromDB"),
        ];
    }
    
    public static function GetPropertyFieldHtml(array $arProperty, $value, $strHTMLControlName)
    {
		\CModule::IncludeModule("iblock");
		\CJSCore::Init('jquery');
		$arFilter = ["IBLOCK_ID" => self::SOURCE_IBLOCK_ID];
		$sections = \CIBlockSection::GetList(Array("left_margin"=>"asc"), $arFilter);
		$arEls = [];
		$curEl = '';
		$DEPTH_LEVEL = 0;
		while($el = $sections->GetNext()){
			$arEls[$el['ID']] = str_repeat('.', $el['DEPTH_LEVEL']) . ' ' . $el['NAME'];
		}
		$currentMaxValue = 10;
		ob_start();?>

			<script type="text/javascript">
				var currentMaxValue = <?=$currentMaxValue?>;
				function appendOrderedElement(el){
					if ($(el).closest('tr').attr('isnew') == 'yes'){
						var c_ord_table = document.getElementById("tr_<?$strHTMLControlName['VALUE']?>");
						var c_ord_table_row = el.closest('tr').cloneNode(true);
						c_ord_table_row.innerHTML = c_ord_table_row.innerHTML.replace('Добавить', 'Удалить');
						$(c_ord_table_row).attr('isnew', 'no');
						$(c_ord_table_row).removeAttr('id');
						$(c_ord_table_row).find('input[type="text"]').val(currentMaxValue);
						console.log($("#tr_<?$strHTMLControlName['VALUE']?>_source select").val());
						console.log($(c_ord_table_row).find('select'));
						$(c_ord_table_row).find('select').val($("#tr_<?$strHTMLControlName['VALUE']?>_source select").val());
						$("#tr_<?$strHTMLControlName['VALUE']?>_source select").val(0);
						$(c_ord_table_row).insertBefore("#tr_<?$strHTMLControlName['VALUE']?>_source");
						currentMaxValue+=10;
						$("#tr_<?$strHTMLControlName['VALUE']?>_source input[type='text']").val(currentMaxValue);
					} else {
						$(el).closest('tr').remove();
					}
				}
			</script>
				<?
					$currentList = [];

					foreach($value['VALUE']['ord'] as $key=>$ord){
						if ($value['VALUE']['field'][$key] != 0){
							$currentList[] = ['ord'=> $ord, 'key'=>$key, 'value'=>$value['VALUE']['field'][$key]];
							if ($currentMaxValue<$ord){
								$currentMaxValue = $ord+10;
							}
						}
					}
					usort($currentList, function($a, $b){
							if ($a['ord'] > $b['ord']) return 1;
							return -1;
					});

				?>

				<table style="width:200px" id="tr_<?$strHTMLControlName['VALUE']?>">
				<thead>
					<tr>
						<td>Сорт</td>
						<td>Блок</td>
					</tr>
				</thead>
				<tbody>
					<?foreach($currentList as $ord=>$item){?>
						<tr isnew="no">
							<td><input name="<?=$strHTMLControlName['VALUE']?>[ord][<?=$item['key']?>]" id="sourceOrderInput" type="text" value="<?=$item['ord']?>" style="width:40px"></td>
							<td>
								<select  name="<?=$strHTMLControlName['VALUE']?>[field][<?=$item['key']?>]">
									<option value="0">-</option>
									<?foreach($arEls as $id=>$el){?>
									<option <?if ($id==$item['value']){?> selected="selected" <?}?> value="<?=$id?>"><?=$el?></option>
									<?}?>
								</select> 
							</td>
							<td><span  onclick="appendOrderedElement(this);return false;" style="cursor:pointer;border-bottom:1px dashed blue; text-decoration: none;">Удалить</span></td>
						</tr>
					<?}?>
					<tr isnew="yes" id="tr_<?$strHTMLControlName['VALUE']?>_source">
						<td><input name="<?=$strHTMLControlName['VALUE']?>[ord][]" id="sourceOrderInput" type="text" value="<?=$currentMaxValue?>" style="width:40px"></td>
						<td>
							<select  name="<?=$strHTMLControlName['VALUE']?>[field][]">
								<option value="0">-</option>
								<?foreach($arEls as $id=>$el){?>
									<option value="<?=$id?>"><?=$el?></option>
								<?}?>
							</select> 
						</td>
						<td><span  onclick="appendOrderedElement(this);return false;" style="cursor:pointer;border-bottom:1px dashed blue; text-decoration: none;">Добавить</span></td>
					</tr>
				</tbody>
			</table>
		<? return ob_get_clean();
    }
    
    public static function GetPropertyHtml($arProperty, $value, $strHTMLControlName)
    {
        $ret = $strHTMLControlName["VALUE"].$value['VALUE'];
        return $ret;
    }

    public static function ConvertToDB($arProperty, $arValue)
    {
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
	}
    protected function _getPropertyFieldHtml(array $arProperty, $value, $strHTMLControlName)
    {
        return $this->glueHtmlContent($htmlContentParts);
    }

    protected function getCrmContactPropertiesHtml($handlerValue, $handlerStrHTMLControlName)
    {
        $html = '';
        return $html;
    }
    
    protected function getCrmContactFieldFormName($handlerStrHTMLControlName)
    {
        return $handlerStrHTMLControlName['VALUE'] . "[CONTACT][]";
    }
    
    protected function getCrmDealPropertiesHtml($handlerValue, $handlerStrHTMLControlName)
    {
        $html = "";
        return $html;
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
        $content = ob_get_clean();
        return $content;
    }
    protected function glueHtmlContent(array $htmlContentParts)
    {
        return implode("", array_map("trim", $htmlContentParts));
    }
}
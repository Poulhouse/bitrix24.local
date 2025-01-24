<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);

//use \Bitrix\Main\Loader;

\Bitrix\Main\Loader::includeModule('iblock');

if (0 < $arResult["SECTIONS_COUNT"])
{
	//print_r($arResult);
	?>
	<table class="table table-bordered table-hover align-middle">
		<thead class="table-light">
		<tr>
			<th>Название операции</th>
<!--			<th>Статус операции-->
<!--				<a href="#" class="d-inline-block" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Подсказка внизу">-->
<!--					<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-question-circle" viewBox="0 0 16 16">-->
<!--						<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>-->
<!--						<path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286zm1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94"/>-->
<!--					</svg>-->
<!--				</a>-->
<!--			</th>-->
			<th>Точка интеграции</th>
			<th>Ошибки за сегодня</th>
			<th>Вызовов за сегодня</th>
			<th>Вызовов за последний месяц</th>
			<th>Вызовов за все время</th>
		</tr>
		</thead>
		<tbody>
		<?
		foreach ($arResult['SECTIONS'] as &$arSection)
		{
			$sectionID = $arSection['ID'];
			$iblockID = $arSection['IBLOCK_ID'];
			$IBLOCK_TYPE = $arSection['IBLOCK_TYPE_ID'];

			$POINT_OF_INTAGRATION_REQUEST = 'UF_POINT_OF_INTAGRATION_REQUEST'; // Код свойства
			$rsResult = CIBlockSection::GetList(array("SORT" => "ASC"), array("ID" => $sectionID, "IBLOCK_ID" => $iblockID), false, $arSelect = array("ID", "IBLOCK_ID", $POINT_OF_INTAGRATION_REQUEST));
			if ($arrResult = $rsResult -> GetNext())
			{
				$UserField = CUserFieldEnum::GetList(array(), array("ID" => $arrResult[$POINT_OF_INTAGRATION_REQUEST]));
				if($UserFieldAr = $UserField->GetNext())
				{
					$POINT_OF_INTAGRATION_REQUEST = $UserFieldAr['VALUE'];
				}
			}
			//Определяем массив нужных полей элемента
			$arSelect = array(
				"NAME",
				"IBLOCK_SECTION_ID",
				"DATE_CREATE",
				"TIMESTAMP_X",
				"DETAIL_PAGE_URL",
				"PROPERTY_DATETIME_REQUEST",
				"PROPERTY_FLAG_ERRORS", //Выбираем нужное нам свойство
				//"PROPERTY_DATE__REQUEST", //Выбираем нужное нам свойство
				// И все другие какие могут понадобится
				// непосредственно в списке
			);
			//$arSelect = array("*");

			$arFilter = array();



			$arFilterErrorsNow = array(
				'IBLOCK_SECTION_ID' => $sectionID,
				'PROPERTY_FLAG_ERRORS_VALUE' => 'Да',
				'>=PROPERTY_DATETIME_REQUEST_VALUE' => date(
						$DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")),
						mktime(0,0,0,date("m"),date("d"),date("Y"))
				)
			);
			$arFilterNow = array(
				'IBLOCK_SECTION_ID' => $sectionID,
				'>=TIMESTAMP_X' => date(
						$DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")),
						mktime(0,0,0,date("m"),date("d"),date("Y"))
				)
			);
			$arFilterM = array(
				'IBLOCK_SECTION_ID' => $sectionID,
				">=TIMESTAMP_X"=> date(
						$DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")),
						mktime(0,0,0,date("m"),1,date("Y"))
				),
				"<=TIMESTAMP_X"=> date(
						$DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")),
						mktime(0,0,0,date("m"),31,date("Y"))
				)
			);




			if($rsElementsErrorsNow = GetIBlockElementListEx($IBLOCK_TYPE,false,false,array(),array("nPageSize"=>$arSection['ELEMENT_CNT']), $arFilterErrorsNow, $arSelect))
			{
				$countErrorsNow = intval($rsElementsErrorsNow->SelectedRowsCount());
//				echo "<pre>";
//				var_dump($rsElementsErrorsNow);
//				echo "</pre>";
			}

			if($rsElementsNow = GetIBlockElementListEx($IBLOCK_TYPE,false,false,array(),array("nPageSize"=>$arSection['ELEMENT_CNT']), $arFilterNow, $arSelect))
			{
				/*while ($obElement = $rsElementsNow -> GetNextElement()){
					$arElement = $obElement -> GetFields();
//					echo "<pre>";
//					print_r($arElement);
//					echo "</pre>";
				}*/
				//Инициализация постраничного вывода.
				//$rsElementsNow->NavStart($arSection['ELEMENT_CNT']);
				$countNow = intval($rsElementsNow->SelectedRowsCount());
				/*
				if($obElement = $rsElementsNow->GetNextElement())
				{
					//Для каждого элемента:
					do
					{
						$arElement = $obElement->GetFields();
						//Ниже можно пользоваться значениями свойств.
						//Например:
						?>
						<tr><td colspan="7"><? print_r($arElement); ?></td></tr>
						<?
						//echo $arElement["PROPERTY_FLAG_ERRORS"],"";
					}
					while ($obElement = $rsElementsNow->GetNextElement());
				}*/
			}

			if($rsElementsM = GetIBlockElementListEx($IBLOCK_TYPE,false,false,array(),array("nPageSize"=>$arSection['ELEMENT_CNT']), $arFilterM, $arSelect))
			{
				$countM = intval($rsElementsM->SelectedRowsCount());
			}
			?>
			<tr>
				<td>
					<a href="<? echo $arSection['SECTION_PAGE_URL']; ?>" class="text-primary" ><? echo $arSection['NAME']; ?></a><br>
<!--					<small class="text-secondary">ss_sync_fl_update</small>-->
				</td>
<!--				<td class="text-success">все хорошо</td>-->
				<td><?=$POINT_OF_INTAGRATION_REQUEST;?></td>
				<td><?=$countErrorsNow;?></td>
				<td><?=$countNow;?></td>
				<td><?=$countM;?></td>
				<td><?=$arSection['ELEMENT_CNT'];?></td>
			</tr>
			<?
		}
		unset($arSection);
		?>
		</tbody>
	</table>
	<?
}

?>
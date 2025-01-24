<?php
require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
require_once ($_SERVER['DOCUMENT_ROOT'] .'/crest/crest.php');

CBitrixComponent::includeComponentClass("kplab:scpreward");
\Bitrix\Main\Loader::includeModule('tasks');
\Bitrix\Main\Loader::IncludeModule("im");
global $USER;
if($_GET['export_excel'] != 'Y')
{
	$leadDateFrom = "";
	$leadDateTo = "";

	if (isset($_POST['periodvidach']) && $_POST['periodvidach'])
	{
		//$dateFrom = new DateTime($_POST['periodvidachFrom']);
		//$dateTo = new DateTime($_POST['periodvidachTo']);
		$tasksFrom = $_POST['periodvidachFrom'];
		$tasksTo = $_POST['periodvidachTo'];

		$dateFromTo = [
			"FROM" => [
				"LEAD" => $leadDateFrom,
				"DEAL" => $tasksFrom
			],
			"TO" => [
				"LEAD" => $leadDateTo,
				"DEAL" => $tasksTo
			]
		];

		$_monthsList = array(
			"1"=>"Январь","2"=>"Февраль","3"=>"Март",
			"4"=>"Апрель","5"=>"Май", "6"=>"Июнь",
			"7"=>"Июль","8"=>"Август","9"=>"Сентябрь",
			"10"=>"Октябрь","11"=>"Ноябрь","12"=>"Декабрь");

		$monthFrom = $_monthsList[date("n", strtotime($tasksFrom))];
		$monthTo = $_monthsList[date("n", strtotime($tasksTo))];

		echo $monthFrom . " - " . $monthTo;

		$data = array();

		//получаем информацию о каждом рефереале в период
		$referrals = KPlabReports_2::getReferral($dateFromTo, true);

		foreach ($referrals as $key => $referral)
		{
			$sellerTotalSum = 0;

			//если сумма сделок больше 0, то тогда собираем данные для акта
			if($referral['SUM_DEALS'] > 0) {
				$data[$key]['DATE_FROM'] = $tasksFrom;
				$data[$key]['DATE_TO'] = $tasksTo;
				$data[$key]['ID_REFERRAL'] = $referral['ID_REFERRAL'];
				$data[$key]['NAME'] = $referral['NAME'];
				$data[$key]['INN_REFERRAL'] = $referral['INN_REFERRAL'];
				$data[$key]['ID_DEAL_REFERRAL'] = $referral['ID_DEAL_REFERRAL'];
				$data[$key]['SUM_DEALS'] = $referral['SUM_DEALS'];
				$data[$key]['MONTH_ACT'] = $monthFrom;
				$data[$key]['YEAR_ACT'] = date("Y");

				//получаем информацию о каждом селлере в период, по ID реферала(КОМПАНИИ/КОНТАКТА)
				$sellers = KPlabReports_2 ::getSellerByReferralId($referral['ID_REFERRAL'], $dateFromTo);

				foreach ($sellers as $k => $seller) {

					$data[$key]['NAME_SELLER'][$k] = $seller['NAME'];
					$data[$key]['INN_SELLER'][$k] = $seller['INN_SELLER'];
					$data[$key]['DOGOVOR_ZAYMA'][$k] = $seller['DOGOVOR_ZAYMA'];
					$data[$key]['NOMER_DOGOVORA'][$k] = $seller['NOMER_DOGOVORA'];
					$data[$key]['DATA_DOGOVORA_ZAYMA'][$k] = $seller['DATA_DOGOVORA_ZAYMA'];
					$data[$key]['SUMMA_ZAIMA'][$k] = $seller['SUMMA_PO_DOGOVORU'];
					$data[$key]['SUM_SCP_KB'][$k] = $seller['SUM_SCP_KB_LEAD'];
					$sellerTotalSum = $sellerTotalSum + $seller['SUM_SCP_KB_LEAD'];


					$resGetDogovor = CRest::call('crm.item.get', [
						'entityTypeId' => 188,
						'id' => $seller['DOGOVOR_ZAYMA']
					])['result']['item'];
					?>
					<pre>$resGetDogovor: <?print_r($resGetDogovor);?></pre>

					<?php

					$fieldForUpdateDogovor = [
						'ufCrm15_1696885746595' => $seller['SUM_SCP_KB_LEAD'].'.00|RUB',
						'ufCrm15_1696892516353' => $seller['SUM_SCP_KB_LEAD'],
						'ufCrm15_1697065397' => $seller['INN_SELLER']
					];
					unset($resGetDogovor['id']);
					$fields = array_merge($resGetDogovor, $fieldForUpdateDogovor);
					?>
					<pre>$fields: <?print_r($fieldForUpdateDogovor);?></pre>

					<?php

					//Обновляем поле `Размер вознаграждения Реферала` в элементе `Договор займа`
					$resUpdDogovor = CRest::call('crm.item.update',
						[
							'entityTypeId' => 188,
							'id' => $seller['DOGOVOR_ZAYMA'],
							'fields' => $fieldForUpdateDogovor
						]
					);
					?>
					<pre>$resUpdDogovor: <?print_r($resUpdDogovor);?></pre>

					<?php
				}

				$data[$key]['TOTAL_SUM_SCP_KB'] = $sellerTotalSum;
			}
			?>

<!--			<pre>$data[$key]: --><?//print_r($data[$key]);?><!--</pre>-->

			<?php
		}
		?>

<!--		<pre>$data: --><?//print_r($data);?><!--</pre>-->



	<?php
		$select = ['*'];
		$order = ['id' => 'ASC'];
		$filter = [
			"!=stageId" => "DT157_88:UC_NIGF33"
		];

		$defaultFields = CRest::call('crm.item.fields',array('entityTypeId'=>157));
		//print_r($defaultFields);

		$listItems = CRest::call('crm.item.list', array(
			'entityTypeId' => 157,
			$select,
			$order,
			$filter
		))['result']['items'];

		$listItemsNew = CRest::call('crm.item.list', array(
			'entityTypeId' => 157,
			$select,
			$order,
			[
				"=stageId" => "DT157_88:UC_NIGF33"
			]
		))['result']['items'];
		?>

<!--		<pre>$listItems: --><?//print_r($listItems);?><!--</pre>-->

		<?php
		$result = array();
		$resultUpdate = array();
		$resultD = array();


		foreach ($data as $k => $item)
		{


			foreach ($listItems as $l => $listItem)
			{
				$StartDate = date("Y-m-d", strtotime($listItem['ufCrm47StartDate']));
				$EndDate = date("Y-m-d", strtotime($listItem['ufCrm47EndDate']));

				if ($StartDate !== $item['DATE_FROM'] && $EndDate !== $item['DATE_TO'] && $listItem['ufCrm47_1680175270670'] == $item['INN_REFERRAL'])
				{

					$result[$k] = $item;
					//$result[$k]['idsmart'] = $listItem['id'];

					//echo "UPDATE: resultUpdate[" . $k . "] \n";

				} else {
					$result[$k] = $item;
//					echo "<pre> item:";
//					print_r($item);
//					echo "</pre>";
				}
			}

			foreach ($listItemsNew as $ll => $listItemNew) {
				$StartDate = date("Y-m-d", strtotime($listItemNew['ufCrm47StartDate']));
				$EndDate = date("Y-m-d", strtotime($listItemNew['ufCrm47EndDate']));
				if ($StartDate == $item['DATE_FROM'] && $EndDate == $item['DATE_TO'] && $listItem['ufCrm47_1680175270670'] == $item['INN_REFERRAL'])
				{
					$resultUpdate[$k] = $item;
					$resultUpdate[$k]['idsmart'] = $listItem['id'];
				}
			}
		}

		if(!empty($result)) {
			foreach ($result as $m => $itemm)
			{
				$fieldsForAdd = [
					'title' => $itemm['NAME'] . ' - ' . $itemm['TOTAL_SUM_SCP_KB'] . ' руб.',
					'companyId' => $itemm['ID_REFERRAL'],
					'ufCrm47_1680683105497' => $itemm['NAME_SELLER'],
					'ufCrm47_1696799510585' => $itemm['INN_SELLER'],
					'ufCrm47EndDate' => $itemm['DATE_TO'],
					'ufCrm47StartDate' => $itemm['DATE_FROM'],
					'ufCrm47_1680175270670' => $itemm['INN_REFERRAL'],
					'ufCrm47_1680175532273' => $itemm['SUM_DEALS'],
					'ufCrm47_1706695732' => $itemm['SUM_DEALS']."|RUB",
					'ufCrm47_totalsum' => $itemm['TOTAL_SUM_SCP_KB']."|RUB",
					'ufCrm47_1696799532817' => $itemm['NOMER_DOGOVORA'],
					'ufCrm47_1696799557321' => $itemm['DATA_DOGOVORA_ZAYMA'],
					'ufCrm47_1680683124199' => $itemm['NUM_DATE_DOGOVOR'], //Номер и дата договора займа
					'ufCrm47_1680683140351' => $itemm['SUMMA_ZAIMA'],
					'ufCrm47_1680683166000' => $itemm['SUM_SCP_KB'],
					'ufCrm47_1696883664' => $itemm['DOGOVOR_ZAYMA'],
					'ufCrm47_1696975582' => $itemm['MONTH_ACT'],
					'ufCrm47_1696975571' => $itemm['YEAR_ACT'],
					'ufCrm47_1697053709' => $itemm['ID_DEAL_REFERRAL']
				];

				$resAdd = CRest::call('crm.item.add', array(
					'entityTypeId'=>157,
					'fields' => $fieldsForAdd
				));
			}
		}
		if(!empty($resultUpdate)) {

			foreach ($resultUpdate as $u => $itemu)
			{
				$fieldsForUpdate = [
					'title' => $itemu['NAME'] . ' - ' . $itemu['TOTAL_SUM_SCP_KB'] . ' руб.',
					'companyId' => $itemu['ID_REFERRAL'],
					'ufCrm47_1680683105497' => $itemu['NAME'],
					'ufCrm47_1696799510585' => $itemu['INN_SELLER'],
					'ufCrm47EndDate' => $itemu['DATE_TO'],
					'ufCrm47StartDate' => $itemu['DATE_FROM'],
					'ufCrm47_1680175270670' => $itemu['INN_REFERRAL'],
					'ufCrm47_1706695732' => $itemu['SUM_DEALS']."|RUB",
					'ufCrm47_1680175532273' => $itemu['SUM_DEALS'],
					'ufCrm47_1680175550917' => $itemu['TOTAL_SUM_SCP_KB'],
					'ufCrm47_1696799532817' => $itemu['NOMER_DOGOVORA'],
					'ufCrm47_1696799557321' => $itemu['DATA_DOGOVORA_ZAYMA'],
					'ufCrm47_1680683124199' => $itemu['NUM_DATE_DOGOVOR'], //Номер и дата договора займа
					'ufCrm47_1680683140351' => $itemu['SUMMA_ZAIMA'],
					'ufCrm47_1680683166000' => $itemu['SUM_SCP_KB'],
					'ufCrm47_1696975582' => $itemu['MONTH_ACT'],
					'ufCrm47_1696975571' => $itemu['YEAR_ACT'],
					'ufCrm47_1697053709' => $itemu['ID_DEAL_REFERRAL']
				];?>

<!--				<pre>$fieldsForUpdate: --><?//print_r($fieldsForUpdate);?><!--</pre>-->

				<?php
				$resUpdate = CRest::call('crm.item.update', array(
					'entityTypeId' => 157,
					'id' => $itemu['idsmart'],
					'fields' => $fieldsForUpdate
				));
				?>

<!--				<pre>$resAdd: --><?//print_r($resUpdate);?><!--</pre>-->

				<?php
			}
		}


/*
 * elseif($StartDate !== null
						&& $EndDate !== null
						&& $listItem['ufCrm47_1680175270670'] !== $item['INN_REFERRAL']) {
						$newEl = true;
						echo "NEW Element: result[" . $k . "] \n";
						$result[$k] = $item;
				}
 *
 * */
	}
}
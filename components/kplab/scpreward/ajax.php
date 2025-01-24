<?php
//if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
//require_once(__DIR__ . '/crest.php');

CBitrixComponent::includeComponentClass("kplab:scpreward");

\Bitrix\Main\Loader::includeModule('tasks');
\Bitrix\Main\Loader::IncludeModule("im");

define("LOG_SCPREWARD", $_SERVER['DOCUMENT_ROOT']."/local/scpreward_ajax.log");

use KPLab\Logs;

global $USER;

use \Bitrix\Im\Department;

if($_GET['export_excel'] != 'Y') {

	//print_r($_POST);

	if(isset($_POST['periodvidach']) && $_POST['periodvidach']){
		$dateFrom = new DateTime($_POST['periodvidachFrom']);
		$dateTo = new DateTime($_POST['periodvidachTo']);
		$tasksFrom = date($_POST['periodvidachFrom']);
		$tasksTo = date($_POST['periodvidachTo']);
	}
	else {
		$dateFrom = date('2000-01-01');
		$dateTo = date('Y-m-d');
		$tasksFrom = "";
		$tasksTo = "";
		//$tasksFrom = $dateFrom->format('d.m.Y') . '00:00:00';
		//$tasksTo = $dateTo->format('d.m.Y') . '23:59:59';
	}

    if(isset($_POST['periodlead']) && $_POST['periodlead']){
        $datetimeLeadFrom = new DateTime($_POST['periodleadFrom']);
        $datetimeLeadTo = new DateTime($_POST['periodleadTo']);

        $leadDateFrom = date($_POST['periodleadFrom']);
        $leadDateTo = date($_POST['periodleadTo']);
    }
    else {
        $dateFrom = date('2000-01-01');
        $dateTo = date('Y-m-d');

        $leadDateFrom = "";
        $leadDateTo = "";
    }

	if(isset($_POST['emptyValues']) && $_POST['emptyValues'] == 'on') {
		$empty = true;
	}
	else {
		$empty = false;
	}

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

	$referrals = \KPlabReports_2::getReferral($dateFromTo, $empty);
	//Logs\File::AddMessage($referrals, "referrals", LOG_SCPREWARD);


	if(count($referrals) == 0) {
	    echo "Ничего не найдено!";
        //print_r($dateFromTo);
    }
	?>

	<table id="myTable" border="1" cellspacing="0" class="tableContainer reports-list-table">
		<thead>
			<tr>
				<th rowspan="3" colspan="1" class="reports-first-column reports-head-cell-top">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">№</span>
					</div>
				</th>
				<th rowspan="1" colspan="7" class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Название реферала, ИНН:</span>
					</div>
				</th>
				<th rowspan="3" colspan="1" width="40px" class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Кол-во лидов</span>
					</div>
				</th>
				<th rowspan="3" colspan="1" width="40px" class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Кол-во сделок</span>
					</div>
				</th>
				<th rowspan="3" colspan="1" width="100px" class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Сумма сделки</span>
					</div>
				</th>
				<th rowspan="3" colspan="1" width="40px" class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">SCP-КВ</span>
					</div>
				</th>
				<th rowspan="3" colspan="1" width="80px" class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Сумма SCP-КВ</span>
					</div>
				</th>
				<th rowspan="3" colspan="1" class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Ответственный</span>
					</div>
				</th>
			</tr>
			<tr>
				<th rowspan="1" colspan="7" class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Лиды/Селлеры</span>
					</div>
				</th>
			</tr>
			<tr>
				<!--Лиды/Селлеры 7 колонок-->
				<th class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Дата лида</span>
					</div>
				</th>
				<th class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Название Селлера</span>
					</div>
				</th>
				<th class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">ИНН Селлера</span>
					</div>
				</th>
				<th class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Статус</span>
					</div>
				</th>
				<th class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Номер договора</span>
					</div>
				</th>
				<th class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Дата договора</span>
					</div>
				</th>
				<th class="reports-head-cell">
					<div class="reports-head-cell">
						<span class="reports-head-cell-title">Дата выдачи займа</span>
					</div>
				</th>
				<!--/end Лиды/Селлеры 6 колонок-->

			</tr>
		</thead>
		<tbody id="tbody-content">
		<?php
		foreach ($referrals as $key => $referral) {
			$sellers = \KPlabReports_2::getSellerByReferralId($referral['ID_REFERRAL'],$dateFromTo);
			$m = $key+1;
			?>

			<!-- Реферал -->
			<tr bgcolor="#d6e5cb" class="referral_<?=$referral['ID_REFERRAL'];?> reports-list-item-referral">
				<td colspan="1"><?=$m;?></td>
				<!--Реферал название-->
				<td colspan="7" id="NAME_REFERRAL">
					<?=$referral['NAME'];?>

					<?if(count($sellers)){ ?>

						<a class="turnSpoiler" href="javascript:void(0)" onclick="turnSpoiler(<?=$referral['ID_REFERRAL'];?>);">список лидов/селлеров</a>

					<?}?>

				</td>
				<!--Количество лидов-->
				<td id="COUNT_LEADS"><?=$referral['COUNT_LEADS'];?></td>
				<!--Количество сделок -->
				<td id="COUNT_DEALS"><?=$referral['COUNT_DEALS'];?></td>
				<!--Сумма сделок -->
				<td id="SUM_DEALS"><?=$referral['SUM_DEALS'];?></td>
				<!--SCP-КВ -->
				<td id="SCP_KB"><?=$referral['SCP_KB'];?>%</td>
				<!--Сумма SCP-КВ -->
				<td id="SUM_SCP_KB"><?=$referral['SUM_SCP_KB'];?></td>
				<!--Ответственный-->
				<td id="ASSIGN">
					<?
						$rsUser = CUser::GetByID($referral['ASSIGN']);
						$arUser = $rsUser->Fetch();
						echo $arUser['LAST_NAME'].' '.$arUser['NAME'];
					?>
				</td>
			</tr>

		<?
			foreach ($sellers as $k => $seller) {
				$t = $k + 1;
				?>

	        <!-- лид/селлер -->
			<tr class="reports-list-item-seller tablerow sellers-list-item<?=$referral['ID_REFERRAL'];?>">
				<td><?=$m.'.'.$t;?></td>
				<td><?=$seller['DATE_LEAD'];?></td>
				<td><?=$seller['NAME'];?></td>
				<td><?=$seller['INN_SELLER'];?></td>
				<td><?=$seller['STATUS'];?></td>
				<td><a href="https://crm.sodeistvie.su/page/zaymy/dogovor_zayma/type/188/details/<?=$seller['DOGOVOR_ZAYMA'];?>/"><?=$seller['NOMER_DOGOVORA'];?></a></td>
				<td><?=$seller['DATA_DOGOVORA_ZAYMA'];?></td>
				<td><?=$seller['DATA_VYDACHI_DZ'];?></td>
				<td>1</td>
				<td><?if($seller['DATA_DOGOVORA_ZAYMA'] !== "") {echo "1";}else{echo "0";}?></td>
				<td><?=$seller['SUMMA_PO_DOGOVORU']. " руб.";?></td>
				<td><?=$seller['SCP_KB_LEAD'];?>%</td>
				<td><?=$seller['SUM_SCP_KB_LEAD']. " руб.";?></td>
				<td>
					<?
					$rsUserLead = CUser::GetByID($seller['ASSIGN_LEAD']);
					$arUserLead = $rsUserLead->Fetch();
					echo $arUserLead['LAST_NAME'].' '.$arUserLead['NAME'];
					?>
				</td>
			</tr>

	            <?
			}
		}
		?>



		</tbody>
		<tfoot>
		<tr>
			<td rowspan="1" colspan="8" class="reports-foot-cell">Итого:</td>

			<td rowspan="1" colspan="1" class="reports-foot-cell" id="totalLeads">
				0
			</td>

			<td rowspan="1" colspan="1" class="reports-foot-cell" id="totalDeals">
				0
			</td>

			<td rowspan="1" colspan="1" class="reports-foot-cell" id="totalSumDeals">
				0 руб.
			</td>

			<td rowspan="1" colspan="1" class="reports-foot-cell"></td>

			<td rowspan="1" colspan="1" class="reports-foot-cell" id="totalSumSCPKB">
				0 руб.
			</td>

			<td rowspan="1" colspan="1" class="reports-foot-cell"></td>
		</tr>
		</tfoot>
	</table>
		<?

		/*if(($emptyValues == 'on') && ($countT == 0)) { ?>
			<script>
                $('#report-result-table').remove();
                $('#tableContentContainer').append('<div id="emptyTasks"><div id="emptyTasksList"><div ' +
                    'class="mainText">Задач не ' +
                    'найдено</div><div class="pseudoText">Попробуйте изменить запрос поиска</div></div></div>');
			</script>
		<? }*/
		?>

	<script type="text/javascript">
        $( document ).ready( function() {
            var $totalLeads = 0;
            var $totalDeals = 0;
            var $totalSumDeals = 0;
            var $totalSumSCPKB = 0;

            $( "#myTable tbody tr.reports-list-item-referral").each( function( index ) {
                $totalLeads += $( this ).children().eq( 2 ).text() * 1;
                $totalDeals += $( this ).children().eq( 3 ).text() * 1;
                $totalSumDeals += $( this ).children().eq( 4 ).text() * 1;
                $totalSumSCPKB += $( this ).children().eq( 6 ).text() * 1;
            });
            $totalSumSCPKB = String($totalSumSCPKB).replace(/(\d)(?=(\d{3})+([^\d]|$))/g, "$1 ") + " руб.";
            $totalSumDeals = String($totalSumDeals).replace(/(\d)(?=(\d{3})+([^\d]|$))/g, "$1 ") + " руб.";
            $( "tfoot td#totalLeads" ).text( $totalLeads );
            $( "tfoot td#totalDeals" ).text( $totalDeals );
            $( "tfoot td#totalSumDeals" ).text( $totalSumDeals );
            $( "tfoot td#totalSumSCPKB" ).text( $totalSumSCPKB );
        });
	</script>
    <script type="text/javascript">
            $( document ).ready( function() {
                var $sumTasks = 0;
                var $sumExpiredTasks = 0;
                $( "#report-result-table tbody tr").each( function( index ) {
                    $sumTasks += $( this ).children().eq( 1 ).text() * 1;
                    $sumExpiredTasks += $( this ).children().eq( 2 ).text() * 1;
                });

                $defAllExpiredCount = $sumExpiredTasks / $sumTasks * 100;

                if(isNaN($defAllExpiredCount)) {
                    $defAllExpiredCount = 0;
                } else {
                    $defAllExpiredCount = $defAllExpiredCount.toFixed(1);
                }

                $( "td#sumTasks" ).text( $sumTasks );
                $( "td#sumExpiredTasks" ).text( $sumExpiredTasks );
                $( "td#defAllExpiredCount" ).text( $defAllExpiredCount + '%' );
            });
		</script>

    <?php

}
elseif($_GET['export_excel'] == 'Y') {
	?>
	<?=$_REQUEST();?>

	<?
}

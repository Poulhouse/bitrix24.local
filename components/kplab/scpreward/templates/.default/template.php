<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die(); ?>

<style>
	.reports-list-table th {
		background: none repeat scroll 0 0 #F4F0D2;
		border-top: 1px solid #ece8cb;
		border-right: 1px solid #ece8cb;
		color: #58564C;
		font: 12px var(--ui-font-family-primary, var(--ui-font-family-helvetica));
		overflow: hidden;
		padding: 6px 12px 6px;
		text-align: left;
	}

	.reports-list-table tfoot td {
		background: none repeat scroll 0 0 #F4F0D2;
		border-top: 1px solid #ece8cb;
		border-right: 1px solid #ece8cb;
		color: #58564C;
		font: 12px var(--ui-font-family-primary, var(--ui-font-family-helvetica));
		overflow: hidden;
		padding: 6px 12px 6px;
		text-align: left;
	}

	.workarea-content-paddings {
		padding: 0px 15px 20px;
		margin-top: 15px;
		margin-bottom: 15px;
	}
	#myTable thead {
		position: sticky;
		top: 0;
		z-index: 9;
	}
	#myTable tfoot {
		position: sticky;
		bottom: -20px;
		z-index: 9;
	}
	a.turnSpoiler {
		float: right;
		color: #aaa;
		text-decoration: underline dashed 1px #aaa;
	}
	.tablerow {
		display: none;
	}
	.tablerow.showlist {
		display: table-row;
	}
	span.depStatus {
		color: #aaa;
		font-style: italic;
	}
</style>
<?php

CJSCore::Init(['ajax']);
$GLOBALS['APPLICATION']->SetAdditionalCSS('/bitrix/js/report/css/report.css');
$GLOBALS['APPLICATION']->SetAdditionalCSS('/local/components/kplab/scpreward/templates/.default/assets/css/style.css');

\Bitrix\Main\Loader::includeModule('ui');
CBitrixComponent::includeComponentClass("kplab:scpreward");

\Bitrix\UI\Toolbar\Facade\Toolbar::addButton([
	"text" => "",
	"menu" => [
		"items" => [
			[
				"onclick" => new \Bitrix\UI\Buttons\JsCode(
					"export_Excel('myTable','Отчёт по задачам', 'report.xls')"
				),
				"text" => "Экспорт в Excel",
				'className'=>'tasks-interface-filter-icon-excel',
				/*"class" => new \Bitrix\UI\Buttons\ButtonAttributes::addClass([
						'class'=>'tasks-interface-filter-icon-excel',
						]),*/
				//'/local/components/kplab/reports/ajax
				//.php?export_excel=Y'//new \Bitrix\UI\Buttons\JsCode("exportExcel()")
			]
		],
	],
	"color" => \Bitrix\UI\Buttons\Color::LIGHT_BORDER,
	"size" => \Bitrix\UI\Buttons\Size::LARGE,
	"icon" => \Bitrix\UI\Buttons\Icon::SETTINGS,
	'dropdown' => false
]);

\Bitrix\UI\Toolbar\Facade\Toolbar::addButton([
	"click" => new \Bitrix\UI\Buttons\JsCode(
		"sendFormSubmit()"
	),
	"text" => "Сформировать отчет",
	"color" => \Bitrix\UI\Buttons\Color::SUCCESS,
	"icon" => \Bitrix\UI\Buttons\Icon::START,
	"size" => \Bitrix\UI\Buttons\Size::LARGE
]);
?>
<?php if($arParams['AJAX'] != 'Y') {?>

<div id="generalReport">
	<?}?>
	<form id="generateReport" method="post" action="<?php echo $_SERVER['PHP_SELF'];?>">
		<input type="hidden" id="userStatus" name="userStatus" value="FIO1"/>
		<div class="left-column">
			<div class="lineFilter">
				<div class="parametrFilter mr-30">
                    <div class="parametrFilter_item ui-ctl">
                        <input type="checkbox" id="periodvidach" name="periodvidach" class=""/>
                        <label for="periodvidach">Период выдач займов: <?="&nbsp;";?></label>
                        <input type="date" id="periodvidachFrom" name="periodvidachFrom" class="setPeriodTasks ui-ctl-element mr-10"/>
                        <?="&ndash;";?>
                        <input type="date" id="periodvidachTo" name="periodvidachTo" class="setPeriodTasks ui-ctl-element
                         ml-10 mr-30"/>
                        <button type="button" class="ui-btn ui-btn-success ui-btn-icon-download setPeriodTasks"
                                onclick="scp_kb()">Выгрузка итогов для актов</button>
                    </div>
                    <div class="parametrFilter_item ui-ctl">
                        <input type="checkbox" id="periodlead" name="periodlead" class=""/>
                        <label for="periodlead">Дата лида: <?="&nbsp;";?></label>
                        <input type="date" id="periodleadFrom" name="periodleadFrom" class="setPeriodTasks ui-ctl-element mr-10"/>
                        <?="&ndash;";?>
                        <input type="date" id="periodleadTo" name="periodleadTo" class="setPeriodTasks ui-ctl-element ml-10 mr-30"/>
                    </div>
				</div>
                <div>
                </div>
			</div>
			<div class="lineFilter">
				<div class="parametrFilter ui-ctl">
                    <div class="parametrFilter_item ui-ctl">
					    <label for="emptyValues">Убрать "Без договоров":</label>
					    <input type="checkbox" id="emptyValues" name="emptyValues" class=""/>
                    </div>
				</div>
				<select name="fieldOrderUsersHidden" id="fieldOrderUsersHidden" class="ui-ctl-element">
					<option selected>Выбрать поле сортировки</option>
					<option value="referralName"> Реферал название</option>
					<option value="countAllLeads"> Кол-во лидов </option>
					<option value="countAllDeals"> Кол-во сделок </option>
					<option value="defAllExpiredCount"> Суммы сделок </option>
				</select>
				<select id="orderUsersHidden" name="orderUsersHidden" required class="ui-ctl-element ">
					<option selected value="DESC">По возрастанию</option>
					<option value="ASC">По убыванию</option>
				</select>

			</div>

		</div>
		<!--
		<span class="ui-btn-split ui-btn-primary">
					<button type="submit" class="ui-btn-main">Сформировать отчет по ответственным</button>
				</span>
		-->
		<hr>
	</form>
	</br>
	<div id="reportDocument">
		<div id="preloader" class="example-overlay" style="display:none;">Отчет формируется, пожалуйста подождите...</div>
		<div id="preloader2" class="example-overlay" style="display:none;">Происходит выгрузка данных, пожалуйста
			подождите..
			.</div>
		<div class="overlay">
			<div id="tableContentContainer">
				Сформируйте отчет
			</div>
		</div>
		<div id="act_uslugi" style="display: none"></div>
	</div>
	<?//print_r($arResult['DEPARTMENTS']);?>

	<?if ($arParams['AJAX'] != 'Y') {?>
</div>
<?}?>

<script type="text/javascript">

    function turnSpoiler(id) {
        var classItem = '.sellers-list-item'+id;
        //$(this).text('Развернуть/Свернуть списко задач');
        $(classItem).toggleClass('showlist');
    }

    function sendFormSubmit() {
        $orderUsers = $('#orderUsers').val();
        $('#orderUsersHidden').val($orderUsers);

        $fieldOrderUsers = $('#fieldOrderUsers').val();
        $('#fieldOrderUsersHidden').val($fieldOrderUsers);

        $("#generateReport").submit();
    }

    function scp_kb() {
        $table = $("#myTable");
        if($table.children().length == 0) {
            alert('Сначала сформируйте отчет');
        } else {
            $.ajax({
                type: "POST",
                url: '/local/components/kplab/scpreward/scp_kb_ajax.php',
                data: $("#generateReport").serialize(),
                beforeSend: function () {
                    isProcessing = true;
                    $height_workarea = $('#workarea-content').outerHeight();
                    $('#reportDocument').css('height', $height_workarea - 100 + 'px');
                    $("#preloader2").show();
                    $('.overlay').css('opacity', '0.5');
                    console.log('Processing scp_kb_ajax.php');
                },
                success: function (response) {

                    //console.log(response);
                    //$('#reportDocument').css('height','auto');
	                $('#act_uslugi').empty().append(response);
                    $('.overlay').css('opacity', '1');
                    $("#preloader2").hide();
                    isProcessing = false;

                },
                error: function () {
                    alert('ajax call failed...');
                }
            });
        }
    }

    function export_Excel(table, name, fileName) {

        $table = $("#myTable");

        //console.log($table);

        if($table.children().length === 0) {
            alert('Сначала сформируйте отчет');
        } else {

            let downloadURI = function (uri, name) {
                let link = document.createElement("a");
                link.download = name;
                link.href = uri;
                link.click();
            }

            let tableToExcel = (function() {

                //console.log(document.getElementById('myTable'));

                let uri = 'data:application/vnd.ms-excel;base64,'
	                template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' +
                    '<head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/></head>' +
                    '<body><table>{table}</table></body></html>',
	                base64 = function (s) { return window.btoa(unescape(encodeURIComponent(s))) },
	                format = function (s, c) { return s.replace(/{(\w+)}/g, function (m, p) { return c[p]; }) }

	            //let resuri = uri + base64(format(template, ctx));

                if (!table.nodeType) table = document.getElementById('myTable')
                let ctx = {
                    worksheet: name || 'Worksheet',
                    table: table.innerHTML
                }
                console.log(ctx);

                return uri + base64(format(template, ctx)); //function(table, name) {

                    //window.location.href = uri + base64(format(template, ctx))
                //}
            })();
            downloadURI(tableToExcel, fileName)
        }
    }

    function getDate(){
        var $today = new Date();
        var $dd = $today.getDate();
        var $mm = $today.getMonth()+1; //January is 0!
        var $yyyy = $today.getFullYear();

        if($dd<10) {
            $dd = '0'+$dd;
        }
        if($mm<10) {
            $mm = '0'+$mm;
        }
        $today = $yyyy + '-' + $mm + '-' + $dd;

        return $today;
    }

    function getCurrentMonth() {
        var $today = new Date();
        var $mm = $today.getMonth()+1; //January is 0!
        var $yyyy = $today.getFullYear();
        if($mm<10) {
            $mm = '0'+$mm;
        }
        $currentMonth = $yyyy + '-' + $mm + '-' +'01';
        return $currentMonth;
    }

    $(document).ready(function() {

        $('#periodvidachFrom').val(getCurrentMonth());
        $('#periodvidachTo').val(getDate());
        $('#periodleadFrom').val(getCurrentMonth());
        $('#periodleadTo').val(getDate());



        $('#generateReport').submit(function(e) {
            e.preventDefault();
            $data = $(this).serialize();
            $.ajax({
                type: "POST",
                url: '/local/components/kplab/scpreward/ajax.php',
                data: $data,
                beforeSend: function() {
                    isProcessing = true;
                    $height_workarea = $('#workarea-content').outerHeight();
                    $('#reportDocument').css('height',$height_workarea-100 +'px');
                    $("#preloader").show();
                    $('.overlay').css('opacity','0.5');
                    console.log('Processing input: ');
                    console.log($data);
                },
                success: function(response)
                {
                    //console.log(response);
                    //$('#reportDocument').css('height','auto');
                    $('.overlay').css('opacity','1');
                    $('#tableContentContainer').empty().append(response);
                    $("#preloader").hide();
                    isProcessing = false;

                },
                error: function() {
                    alert('ajax call failed...');
                }
            });
        });
    });

</script>
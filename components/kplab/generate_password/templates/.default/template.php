<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die(); ?>

<style>

	.workarea-content-paddings {
		padding: 0px 15px 20px;
		margin-top: 15px;
		margin-bottom: 15px;
	}

    .value {
        display: block;
        margin-left: 10px;
        font-size: 14pt;
    }
    #pswdValue {
        color: var(--ui-field-color-focused);
    }
    #idValue {
        color: var(--ui-field-color-focused);
    }
    #errorValue {
        color: var(--ui-field-color-warning);
    }
    .row-custom-form {
        display: inline-flex;
        width: 100%;
        align-content: center;
        flex-direction: row;
    }

    .customform {
        width: 30%;
        padding: 16px 8px;
    }

    .customform input {
        margin-bottom: 10px;
    }

</style>
<?php

CJSCore::Init(['ajax']);

\Bitrix\Main\Loader::includeModule('ui');
CBitrixComponent::includeComponentClass("kplab:generate_password");

?>
<?php if($arParams['AJAX'] != 'N') {?>

<div id="generatePassword">

<?php }?>

	<div class="row-custom-form">
		<form class="customform" id="generatepswd" method="post" action="">

			<input type="tel" id="phone" name="phone" class="ui-ctl-element mr-10 mb-10" placeholder="Enter phone number"/>

			<button type="submit" id="generatePswBtn" class="ui-btn ui-btn-success">Сгенерировать пароль</button>

			<div class="value">
				Пароль: <span id="pswdValue"></span> <br/>
				ID Компании: <span id="idValue"></span><br/>
				Ошибка: <span id="errorValue"></span>
			</div>

		</form>

		<form class="customform"  id="getPswd" method="post" action="">

			<input type="text" id="companyId" name="companyId" class="ui-ctl-element mr-10 mb-10" placeholder="Enter ID Company"/>

			<button type="button" id="getPswBtn" class="ui-btn ui-btn-success">Узнать пароль (без СМС)</button>
			<button type="button" id="setPswBtn" class="ui-btn ui-btn-success">Заменить пароль (без СМС)</button>

			<div class="value">
				Пароль: <span id="pswdValue2"></span> <br/>
				ID Компании: <span id="idValue2"></span><br/>
				Ошибка: <span id="errorValue2"></span>
			</div>

		</form>
	</div>
<?php if ($arParams['AJAX'] != 'Y') {?>

</div>

<?php }?>

<script type="text/javascript">

    $(document).ready(function() {
        $('#generatepswd').submit(function(e) {
            e.preventDefault();
            $.ajax({
                type: "POST",
                url: '/local/components/kplab/generate_password/ajax.php',
                data: $(this).serialize(),
                dataType: "json",
                beforeSend: function () {
                    isProcessing = true;
                    $('#generatePswBtn').text('Идет генерация пароля... Подождите.');
                    console.log('Processing generate_password/ajax.php');
                },
                success: function (response) {
                    $('#pswdValue').empty().append(response.psw);
                    $('#idValue').empty().append(response.id);
                    $('#errorValue').empty().append(response.error);
                    $('#generatePswBtn').text('Сгенерировать пароль');
                    isProcessing = false;
                },
                error: function () {
                    alert('ajax call failed...');
                }
            });
        });
        $('#getPswBtn').on('click',function(e) {
            e.preventDefault();
            $.ajax({
                type: "POST",
                url: '/local/components/kplab/generate_password/ajax_getpswd.php',
                data: $('#getPswd').serialize(),
                dataType: "json",
                beforeSend: function () {
                    isProcessing = true;
                    $('#getPswBtn').text('Идет поиск пароля... Подождите.');
                    console.log('Processing generate_password/ajax_getpswd.php');
                },
                success: function (response) {
                    $('#pswdValue2').empty().append(response.psw);
                    $('#errorValue2').empty().append(response.error);
                    $('#getPswBtn').text('Узнать пароль (без СМС)');
                    isProcessing = false;
                },
                error: function () {
                    alert('ajax call failed...');
                }
            });
        });
        $('#setPswBtn').on('click',function(e) {
            e.preventDefault();
            $.ajax({
                type: "POST",
                url: '/local/components/kplab/generate_password/ajax_setpswd.php',
                data: $('#getPswd').serialize(),
                dataType: "json",
                beforeSend: function () {
                    isProcessing = true;
                    $('#setPswBtn').text('Идет замена пароля... Подождите.');
                    console.log('Processing generate_password/ajax_setpswd.php');
                },
                success: function (response) {
                    $('#pswdValue2').empty().append(response.psw);
                    $('#idValue2').empty().append(response.id);
                    $('#errorValue2').empty().append(response.error);
                    $('#setPswBtn').text('Заменить пароль (без СМС)');
                    isProcessing = false;
                },
                error: function () {
                    alert('ajax call failed...');
                }
            });
        });
    });

</script>
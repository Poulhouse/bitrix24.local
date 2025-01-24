<?if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();?>

<div class="request-column<?=($arResult['SCRIPT'] ? ' left' : ' full')?>">
  <form class="was_bs info">
    <input type="hidden" name="DEAL_ID" value="<?=$arResult['DEAL']['ID']?>">
    <input type="hidden" name="TYPE" class="form_submit_type" value="default">
    
    <!-- Блок перевода звонков -->
    <div class="call-transfer-wrapper">
      <div class="btn btn-primary call-transfer-switch"
           data-block="call-transfer-block" 
           data-action="call_transfer_accompaniment">Перевод с сопровождением</div>
      <div class="btn btn-primary call-transfer-switch" 
           data-block="call-transfer-block" 
           data-action="call_transfer">Перевод без сопровождения</div>
      <div id="call-transfer-form-reset" class="btn btn-danger hide-block" data-block="call-transfer-block">X</div>
      <div class="hidden_blocks call-transfer-block">
        <div id="call-transfer-form">
          <input id="call-transfer-action" type="hidden" name="CALL_TRANSFER_ACTION" value=""/>
          <div class="top-call-data">
            <span id="call-transfer-title" class="form__title">Перевод с сопровождением</span>
            <div class="row">
              <div class="form-group col-md-6">
                <label>Сотрудник:</label>
                <?
                $APPLICATION->IncludeComponent(
                'bitrix:main.user.selector',
                ' ',
                [
                   "ID" => "call-transfer-selector",
                   "API_VERSION" => 3,
                   "INPUT_NAME" => "CALL_TRANSFER_USER_ID",
                   "USE_SYMBOLIC_ID" => true,
                   "BUTTON_SELECT_CAPTION" => "Ok",
                   "SELECTOR_OPTIONS" => 
                    [
                      "departmentSelectDisable" => "Y",
                      'context' => 'CALL_TRANSFER_USER',
                      'contextCode' => 'U',
                      'enableAll' => 'N',
                      'userSearchArea' => 'I'
                    ]
                ]
                );?>
              </div>
              <div class="form-group col-md-6">
                <label>Номер телефона:</label>
                <input id="call-transfer-phone" type="text" class="form-control" name="CALL_TRANSFER_PHONE"/>
              </div>
            </div>
            <button id="call-transfer-form-submit" type="button" class="btn btn-success">Перевести</button>
          </div>
        </div>
      </div>
    </div>
    <!-- /Блок перевода звонков -->
    
    <h4 class="form-title"><?=$arResult['GROUP']['PROPERTIES']['TYPE']['VALUE']?> / <?=$arResult['GROUP']['NAME']?></h4>
    
    <div class="form-section-title">Информация о клиенте <?=$arResult['DEAL']['CONTACT_ID']?></div>
    <div class="form-group top-call-data">
      <div class="call-from">
        Звонок от <?=$arResult['DEAL']['CONTACT']['TYPE']?>
      </div>
      
      <div class="row data-list">
        <?foreach($arResult['DEAL']['PHONES'] as $arPhone){?>
        <div class="col-md-6 data-item">
          <div class="item-name">Телефон (<?=$arPhone['NAME']?>):</div>
          <div class="item-value"><?=$arPhone['VALUE']?></div>
        </div>
        <?}?>
        <?/*?>
        <div class="col-md-6 data-item">
          <div class="item-name">Город:</div>
          <div class="item-value"><?=($arResult['DEAL']['CITY'] ? $arResult['DEAL']['CITY'] : 'отсутствует')?></div>
        </div>
        <?*/?>
        <?foreach($arResult['VIEW_FIELDS'] as $arFields){?>
          <?foreach($arFields as $arField){?>
            <?if($arField['_TYPE'] == 'f'){?>
            <div class="col-md-6 data-item">
              <div class="item-name"><?=$arField['NAME']?>:</div>
              <div class="item-value<?=($arField['_EMPTY'] ? ' not-filled' : '')?>"><?=$arField['VALUE']?></div>
            </div>
            <?}else if($arField['_TYPE'] == 'uf'){?>
            <div class="col-md-6 data-item">
              <div class="item-name"><?=$arField['EDIT_FORM_LABEL']?>:</div>
              <div class="item-value<?=($arField['_EMPTY'] ? ' not-filled' : '')?>">

                <?$APPLICATION->IncludeComponent(
                    "bitrix:system.field.view",
                    $arField['USER_TYPE']['USER_TYPE_ID'],
                    array("arUserField" => $arField),
                    null,
                    array("HIDE_ICONS"=>"Y")
                );?>
              </div>
            </div>
            <?}?>
          <?}?>
        <?}?>
      </div>
      
      <div class="activity-block row">
        <div class="col-md-12 last-activity">
          <div class="activity-title">Последние звонки:</div>
          <ul class="activity-list">
          <?foreach($arResult['EVENTS_LIST_CALLS'] as $arEventItem) {?>
                <li>
                  <span class="date"><?=$arEventItem['CREATED']?></span>
                  <br>
                  <span class="name"><?=$arEventItem['HTML']?></span>
                </li>
          <?}?>
          </ul>
        </div>
      </div>
      
      <?/*?>
      <div class="activity-block row">
        <div class="col-md-6 last-activity">
          <div class="activity-title">Последние события:</div>
          <ul class="activity-list">
            <li><span class="name">Звонок</span><span class="date">22.05.2019 в 15:30</span></li>
            <li><span class="name">Визит</span><span class="date">20.05.2019 в 12:52</span></li>
            <li><span class="name">Звонок</span><span class="date">17.05.2019 в 16:07</span></li>
          </ul>
        </div>
        <div class="col-md-6 last-activity">
          <div class="activity-title">Последние продукты:</div>
          <ul class="activity-list">
            <li><span class="name">Кредитные каникулы</span></li>
            <li><span class="name">Кредитные каникулы</span></li>
            <li><span class="name">Кредитные каникулы</span></li>
          </ul>
        </div>
      </div>
      <?*/?>
    </div>
    
    <?if(strlen($arResult['DEAL']['SPECIAL_OFFER'])){?>
    <div class="offer-block">
      <div class="block-title">Специальное предложение</div>
      <div class="block-text"><?=$arResult['DEAL']['SPECIAL_OFFER']?></div>
    </div>
    <?}?>
    
    <div class="form-section-title">Информация о звонке</div>
	  <div id="mergerer"></div>

    <div class="form-group field_edit">
        <label>Продукт:</label>
        <select id="switch-category" class="custom-select" size="1">
            <?foreach($arResult['CRM_CATEGORIES'] as $arCategory){?>
              <option value="<?=$arCategory['ID']?>" 
                      title="<?=$arCategory['NAME']?>"
                      <?=($arCategory['ID'] == $arResult['DEAL_CATEGORY_ID']) ? 'selected' : ''?>
                      ><?=$arCategory['NAME']?></option>
            <?}?>
        </select>
    </div>
    
    <?foreach($arResult['EDIT_FIELDS'] as $arFields){?>
      <?foreach($arFields as $arField){

          $condition = $arField['CONDITION'];
          $condition_value = $arField['CONDITION_VALUE'];
          $condition_txt = '';
          if($condition){
            $condition_txt = ' data-cond="'. $condition .'" data-val="'. $condition_value .'"';
          }
          
          if ($arField['_TYPE'] == 'uf' && !empty($arField['USER_TYPE']['USE_FIELD_COMPONENT'])) { 
            $arField['USER_TYPE']['USE_FIELD_COMPONENT'] = false;
          }
      ?>
        <?if($arField['_TYPE'] == 'f'){?>
        <div class="form-group field_edit conditional" data-id="<?=$arField['_ID']?>"<?=$condition_txt?>>
          <label><?=$arField['NAME']?>:</label>
			<input name="<?=$arField['CODE']?>" <?if ($arField['CODE'] == 'contact[NAME]' || $arField['CODE'] == 'contact[LAST_NAME]'){?> onchange="checkDeduple('<?=$arResult['DEAL']['CONTACT_ID']?>')" <?}?> class="form-control" value="<?=$arField['VALUE']?>">
        </div>
        <?}else if($arField['_TYPE'] == 'uf'){?>
        <div class="form-group field_edit conditional" data-id="<?=$arField['_ID']?>"<?=$condition_txt?>>

		   <input type="hidden" class="XML_ID" value="<?=$arField['XML_ID']?>" /> 
          <label><?=$arField['EDIT_FORM_LABEL']?>: <?if($arField['MANDATORY'] == 'Y'){?><span class="starrequired">*</span><?}?></label>
          <?$APPLICATION->IncludeComponent(
              "bitrix:system.field.edit",
              $arField['USER_TYPE']['USER_TYPE_ID'],
              array("arUserField" => $arField),
              null,
              array("HIDE_ICONS"=>"Y")
          );?>
        </div>
        <?}?>
      <?}?>
    <?}?>
    
    <?
    $arUserField = $arResult['SYSTEM_UF']['STATUS'];
    $arUserField['USER_TYPE']['USE_FIELD_COMPONENT'] = false;
    ?>
    
    <div class="form-group field_edit">
      <label for="d_<?=$arUserField['FIELD_NAME']?>"><?=$arUserField['EDIT_FORM_LABEL']?>:</label>
      <?$APPLICATION->IncludeComponent(
          "bitrix:system.field.edit",
		  $arUserField['USER_TYPE']['USER_TYPE_ID'],
          array("arUserField" => $arUserField),
          null,
          array("HIDE_ICONS"=>"Y")
      );?>
    </div>
    
    <div class="btn btn-primary show_block" data-block="appointment">Назначить встречу</div>
    <div class="btn btn-primary show_block" data-block="call_back">Перезвонить</div>
    <div class="btn btn-danger show_block" data-block="decline">Не интересует</div>
    
    <div class="additional">
      <div class="hidden_blocks appointment">
        <div class="form-group field_row">
          <?
          $arUserField = $arResult['SYSTEM_UF']['CRM_1615633559'];
          $arUserField['USER_TYPE']['USE_FIELD_COMPONENT'] = false;
          ?>
          <label for="d_<?=$arUserField['FIELD_NAME']?>">Офис:</label>
          <?$APPLICATION->IncludeComponent(
              "bitrix:system.field.edit",
              $arUserField['USER_TYPE']['USER_TYPE_ID'],
              array("arUserField" => $arUserField),
              null,
              array("HIDE_ICONS"=>"Y")
          );?>
        </div>
        <div class="form-group field_row">
          <label>Плановая дата обращения:</label>
          <div class="set_date input-group">
            <div class="input-group-prepend">
              <button class="btn btn-outline-primary set_day" data-date="<?=$arResult['TODAY']?>">Сегодня</button>
              <button class="btn btn-outline-primary set_day" data-date="<?=$arResult['TOMORROW']?>">Завтра</button>
            </div>
            <input type="text" class="form-control" name="MEET_DATE" onclick="BX.calendar({node: this, field: this, bTime: false});" autocomplete="off">
          </div>
        </div>
        <div class="form-group field_row">
          <label for="d_MEET_TIME">Плановое время обращения:</label>
          <select name="MEET_TIME" id="d_MEET_TIME" class="custom-select">
            <?foreach($arResult['TIME_LIST'] as $arItem){?>
            <option value='<?=$arItem['VALUE']?>'><?=$arItem['NAME']?></option>
            <?}?>
          </select>
        </div>
        
        <div class="btn btn-danger hangup">Запланировать</div>
        <button class="btn btn-success">Запланировать и перейти</button>
      </div>
      
      <div class="hidden_blocks call_back">
        <div class="form-group field_row">
          <label>Плановая дата обращения:</label>
          <div class="set_date input-group">
            <div class="input-group-prepend">
              <button class="btn btn-outline-primary set_day" data-date="<?=$arResult['TODAY']?>">Сегодня</button>
              <button class="btn btn-outline-primary set_day" data-date="<?=$arResult['TOMORROW']?>">Завтра</button>
            </div>
            <input type="text" class="form-control" name="CALL_BACK_DATE" onclick="BX.calendar({node: this, field: this, bTime: false});" autocomplete="off">
          </div>
        </div>
        <div class="form-group field_row">
          <label for="d_CALL_BACK_TIME">Плановое время обращения:</label>
          <select name="CALL_BACK_TIME" id="d_CALL_BACK_TIME" class="custom-select">
            <?foreach($arResult['TIME_LIST'] as $arItem){?>
            <option value='<?=$arItem['VALUE']?>'><?=$arItem['NAME']?></option>
            <?}?>
          </select>
        </div>
        
        <div class="btn btn-danger hangup">Запланировать</div>
        <button class="btn btn-success">Запланировать и перейти</button>
      </div>
      
      <div class="buttons">
        <div id="show-sms-templates-btn" class="btn btn-primary">Отправить SMS по шаблону</div>
        <div id="show-email-templates-btn" class="btn btn-primary">Отправить Email по шаблону</div>
      </div>
      
      <div id="send-message-error-block" class="alert alert-danger" style="display:none;"></div>
      <div id="send-message-success-block" class="alert alert-success" style="display:none;"></div>
      
      <div id="email-templates-wrapper" class="mb-2 mt-2 form-row" style="display:none;">
        <div class="col">
          <select id="email-template" class="form-control">
            <option value="">Выберите шаблон</option>
            <?foreach($arResult['EMAIL_TEMPLATES'] as $templateId => $arTemplate){?>
              <option value="<?=$arTemplate['ID']?>"><?=$arTemplate['TITLE']?></option>
            <?}?>
          </select>
        </div>
        <div class="col">
          <select id="email-value" class="form-control">
            <option value="">Выберите Email</option>
            <?foreach($arResult['DEAL']['EMAILS'] as $arEmail){?>
              <option value="<?=$arEmail['VALUE']?>"><?=$arEmail['VALUE']." - ".$arEmail['NAME']?></option>
            <?}?>
          </select>
        </div>
        <div class="col">
          <div id="send-email-btn" class="btn btn-success">Отправить</div>
        </div>
      </div>
      
      <div id="sms-templates-wrapper" class="mb-2 mt-2 form-row" style="display:none;">
        <div class="col">
          <select id="sms-template" class="form-control">
            <option value="">Выберите шаблон</option>
            <?foreach($arResult['SMS_TEMPLATES'] as $templateId => $arTemplate){?>
              <option value="<?=$arTemplate['ID']?>"><?=$arTemplate['TITLE']?></option>
            <?}?>
          </select>
        </div>
        <div class="col">
          <select id="phone-value" class="form-control">
            <option value="">Выберите телефон</option>
            <?foreach($arResult['DEAL']['PHONES'] as $arPhone){?>
              <option value="<?=$arPhone['VALUE']?>"><?=$arPhone['VALUE']." - ".$arPhone['NAME']?></option>
            <?}?>
          </select>
        </div>
        <div class="col">
          <div id="send-sms-btn" class="btn btn-success">Отправить</div>
        </div>
      </div>
      
      <div class="hidden_blocks decline">
        <?$arUserField = $arResult['SYSTEM_UF']['DECLINE'];?>
        <div class="form-group field_row">
          <label for="d_<?=$arUserField['FIELD_NAME']?>"><?=$arUserField['EDIT_FORM_LABEL']?>:</label>
          <?$APPLICATION->IncludeComponent(
              "bitrix:system.field.edit",
              $arUserField['USER_TYPE']['USER_TYPE_ID'],
              array("arUserField" => $arUserField),
              null,
              array("HIDE_ICONS"=>"Y")
          );?>
        </div>
        
        <div class="btn btn-danger hangup">Сохранить</div>
        <button class="btn btn-success">Сохранить и перейти</button>
      </div>
    </div>
    
    <div class="form-group field_edit">
      <label for="d_COMMENTS">Комментарий:</label>
      <textarea id="d_COMMENTS" name="COMMENTS" class="form-control" rows="6"><?=$arResult['DEAL']['COMMENTS']?></textarea>
    </div>
    
    <div class="form-controls">
      <div class="btn btn-danger hangup hangup_main">Положить трубку</div>
      <div class="options">
        <div class="btn btn-primary">Календарь</div>
        <div class="btn btn-primary">Калькулятор</div>
      </div>
    </div>
  </form>
</div>

<div class="request-column right">
  <div class="alert alert-danger d-none countdown"></div>
<?if($arResult['SCRIPT']){?>
  <div class="accordeon-block main-accordeon">
    <?foreach($arResult['SCRIPT']['SECTIONS'] as $arSection){?>
    <div class="accordeon-item">
      <div class="item-header"><?=$arSection['NAME']?></div>
      <div class="item-content">
        <?foreach($arSection['ITEMS'] as $arItem){?>
        <div class="phrases-list">
          <?=$arItem['DETAIL_TEXT']?>
        </div>
        <?}?>
      </div>
    </div>
    <?}?>
  </div>
  
  <ul class="liquid-btns">
    <?if(count($arResult['SCRIPT_PHRASES'])){?>
    <li><div class="tab-label btn btn-primary toggle-blocks" data-block="standart-phrases">Стандартные фразы</div></li>
    <?}?>
    <?if(count($arResult['SCRIPT_OBJECTIONS'])){?>
    <li><div class="tab-label btn btn-primary toggle-blocks" data-block="objections">Возражения</div></li>
    <?}?>
  </ul>
  
  <div class="toggle_blocks_cont">
    <?if(count($arResult['SCRIPT_PHRASES'])){?>
    <div class="hidden_blocks standart-phrases">
      <div class="white-block">
        <?foreach($arResult['SCRIPT_PHRASES'] as $arItem){?>
        <div class="phrases-list">
          <?=$arItem['DETAIL_TEXT']?>
        </div>
        <?}?>
      </div>
    </div>
    <?}?>
    
    <?if(count($arResult['SCRIPT_OBJECTIONS'])){?>
    <div class="hidden_blocks objections">
      <div class="white-block">
        <?foreach($arResult['SCRIPT_OBJECTIONS'] as $arItem){?>
        <div class="phrases-list">
          <?=$arItem['DETAIL_TEXT']?>
        </div>
        <?}?>
      </div>
    </div>
    <?}?>
  </div>

<script>
    $("input[name='contact[UF_CRM_1573482916810]']").suggestions({
        token: "50175a1383dac4d98a45dbbdc14b2fd98efc2ae1",
        type: "ADDRESS",
		bounds: "city-settlement"
        }
    );
</script>
<?}?>
</div>
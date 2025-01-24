<?if(!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Page\Asset;

CUtil::InitJSCore(['window', 'jquery', 'fx', 'ajax', 'popup', 'ui', 'currency', 'core_money_editor',]);

$asset = Asset::getInstance();
$asset->addCss('/bitrix/components/bitrix/crm.interface.filter/templates/title/style.css');
$asset->addCss('/bitrix/components/bitrix/intranet.contact_center.list/templates/.default/style.css');
$asset->addCss('/bitrix/js/crm/kanban/css/kanban.css');
$asset->addCss($this->GetFolder() .'/css/bootstrap.css');
$asset->addCss($this->GetFolder() .'/css/fontawesome.min.css');
$asset->addCss($this->GetFolder() .'/css/magnific-popup.css');
$asset->addJs($this->GetFolder() .'/js/vue.js');
$asset->addJs($this->GetFolder() .'/js/jquery.magnific-popup.min.js');
$asset->addJs($this->GetFolder() .'/js/telephony.js');
$asset->addJs('/bitrix/components/bitrix/currency.money.input/templates/.default/script.js');
$asset->addJs('/bitrix/components/bitrix/ui.tile.selector/templates/.default/script.js');

$APPLICATION->SetPageProperty('BodyClass', 'no-background');
?>

<?$this->SetViewTarget('below_pagetitle');?>
<?/*<div id="markup-pagetitle-below">
  <div class="crm-view-switcher pagetitle-align-right-container">
    <?foreach($arResult['FILTER'] as $arLink){?>
    <div class="crm-view-switcher-list-item">
      <a href="<?=$arLink['LINK']?>" class="<?=$arLink['CURRENT'] ? 'ui-btn ui-btn-light-border ui-btn-no-caps ui-btn-themes ui-btn-round crm-robot-btn' : ''?>"><?=$arLink['NAME']?></a>
    </div>
    <?}?>
  </div>
</div>*/?>
<?$this->EndViewTarget();?>

<div style="display:none">
  <?
  // Для инициализации всех JS библиотек
  $APPLICATION->IncludeComponent(
  'bitrix:main.user.selector',
  '',
  [
     "ID" => "stub",
     "API_VERSION" => 3,
     "INPUT_NAME" => "STUB",
     "USE_SYMBOLIC_ID" => true,
     "BUTTON_SELECT_CAPTION" => "Ok",
     "SELECTOR_OPTIONS" => []
  ]
  );?>
</div>

<div class="was_bs">
  <div id="v_app" class="queue_block" data-ajax_cache_id="<?=$arResult["AJAX_CACHE_ID"]?>" data-auto_call="<?=intval($arResult["AUTO_CALL"])?>">
    <div class="row">
      <div class="spinner_inline">
        <div class="donut"></div>
      </div>
      <div class="col-12 top-btns">
        <div class="btn btn-danger" style="width:200px;" @click.prevent="clear()">Отключиться от всех</div>
      </div>
      
      <div class="col-12 main-content">
        <div class="row">
          <div class="col-auto">
            <div v-for="(item, index) in groups" :class="[{'selected': filter.group_ids.indexOf(item.id) !== -1}, 'group-item']"
              :style="{'background-color': '#'+item.color}" @click.prevent="toggleGroup(item.id)" :key="item.id">
              <div class="total">
                {{item.total_deals}}
              </div>
              <div class="info">
                <div v-if="item.total_deals_expired > 0" class="expired">
                  Просрочено: {{item.total_deals_expired}}
                </div>
				  <div class="title" v-html="item.name">{{item.name}}</div>
              </div>
            </div>
          </div>
          
          <div class="col">
            <transition-group name="deals-list" tag="div" class="row deals-list">
              <div v-for="(item, index) in sortedItems" class="col-12 item" :key="item.id">
                <div class="content" :style="{'border-color': '#'+item.color}">
                  <div class="top">
                    <a class="title" :href="'/crm/deal/details/'+ item.id +'/'">{{item.contact_name}}</a>
                    <span class="expired" v-if="item.planned_call_expired">Просрочена</span>
                    <span class="planned-time">({{item.planned_call}})</span>
                  </div>
                  <ul class="fields">
                    <li><span class="title">Телефон:</span> {{item.contact_phone}}</li>
                    <li v-for="(field, index) in item.display_fields.contact"><span class="title">{{field.name}}:</span> {{field.value}}</li>
                    <li v-for="(field, index) in item.display_fields.deal"><span class="title">{{field.name}}:</span> {{field.value}}</li>
                  </ul>
                  <div class="date">{{item.date_create}}</div>
                  <div :class="[{'d-none': queue_auto_call}, 'btns']">
                    <div class="phone" @click.prevent="dealLoad(item.id)"></div>
                  </div>
                </div>
              </div>
            </transition-group>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?$APPLICATION->IncludeComponent("whatasoft:requests.incoming.edit",
  "",
  Array(),
  $component
);?>
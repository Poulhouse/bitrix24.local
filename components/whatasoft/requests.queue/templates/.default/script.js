function ShowMsg(_msg, _title){
  _title = _title || '';
  popup_id = 'popup_msg';
  if($('#'+popup_id).length <= 0){
    $("body").append('<div id="popup_msg" class="zoom-anim-dialog mfp-hide"><div class="msg-title"></div><div class="msg-text"></div></div>');
  }
  $('#'+popup_id+' .msg-text').html(_msg);
  $('#'+popup_id+' .msg-title').html(_title);
  if(_title.length > 0){
    $('#'+popup_id+' .msg-title').show();
  }else{
    $('#'+popup_id+' .msg-title').hide();
  }

  $.magnificPopup.open({
    items: {
      src: $('#'+popup_id)
    },
    
    type: 'inline',
    fixedContentPos: false,
    fixedBgPos: true,
    overflowY: 'auto',
    closeBtnInside: true,
    preloader: false,
    
    midClick: true,
    removalDelay: 300,
    mainClass: 'my-mfp-slide-bottom'
  });
}

function ShowError(_msg, _title){
  _title = _title || '';
  popup_id = 'popup_error_msg';
  if($('#'+popup_id).length <= 0){
    $("body").append('<div id="'+popup_id+'" class="zoom-anim-dialog mfp-hide"><div class="msg-title"></div><div class="msg-text"></div></div>');
  }
  $('#'+popup_id+' .msg-text').html(_msg);
  $('#'+popup_id+' .msg-title').html(_title);
  if(_title.length > 0){
    $('#'+popup_id+' .msg-title').show();
  }else{
    $('#'+popup_id+' .msg-title').hide();
  }
  
  $.magnificPopup.open({
    items: {
      src: $('#'+popup_id)
    },
    
    type: 'inline',
    fixedContentPos: false,
    fixedBgPos: true,
    overflowY: 'auto',
    closeBtnInside: true,
    preloader: false,
    
    midClick: true,
    removalDelay: 300,
    mainClass: 'my-mfp-slide-bottom'
  });
}

function AddSpinner(_block){
  if($('.spinner_cont',_block).length > 0){
    return;
  }
  spinner = $('<div class="spinner_cont"><div class="spinner"></div></div>');
  width = _block.outerWidth();
  height = _block.outerHeight();
  spinner.width(width);
  spinner.height(height);
  if(_block.css("position") != "absolute"){
    _block.css("position", "relative");
  }
  _block.append(spinner);
}

function RemoveSpinner(_block){
  _block.find('.spinner_cont').remove();
}

$(document).ready(function(){
  
  function SendApiRequest(action, request_params) {
    return new Promise(function(resolve, reject){
      var errors = [];
      var block = $('.request_detail_edit');
      var cache_id = block.data('ajax_cache_id');
      
      var data = {
        action: action,
        cache_id: cache_id,
        session_id: BX.bitrix_sessid()
      };
      
      if (request_params) {
        data = Object.assign(data, request_params);
      }
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function GetQueue(){
    return new Promise(function(resolve, reject){
      var errors = [];
      var block = $(".queue_block");
      var cache_id = block.data("ajax_cache_id");
      
      var data = {
        action: 'get_queue',
        cache_id: cache_id,
        session_id: BX.bitrix_sessid()
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function UpdateUserQueues(_group_ids){
    return new Promise(function(resolve, reject){
      var errors = [];
      var block = $(".queue_block");
      var cache_id = block.data("ajax_cache_id");
      
      var data = {
        action: 'update_user_queues',
        cur_groups : _group_ids,
        cache_id: cache_id,
        session_id: BX.bitrix_sessid()
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function SendMessage(action, to, templateId){
    return new Promise(function(resolve, reject){
      var errors = [];
      var $block = $('.request_detail_edit');
      var cache_id = $block.data('ajax_cache_id');
      
      var data = {
        cache_id: cache_id,
        session_id: BX.bitrix_sessid(),
        action: action,
        to: to,
        template_id: templateId,
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function SendSms(phone, smsTemplateId){
    return SendMessage('send_sms', phone, smsTemplateId);
  }
  
  function SendEmail(email, templateId) {
    return SendMessage('send_email', email, templateId);
  }
  
  function UpdateDealCategory(_deal_id, category_id){
    return new Promise(function(resolve, reject){
      var errors = [];
      var $block = $('.request_detail_edit');
      var cache_id = $block.data('ajax_cache_id');
      
      var data = {
        cache_id: cache_id,
        session_id: BX.bitrix_sessid(),
        action: 'update_deal_category',
        deal_id: _deal_id,
        category_id: category_id,
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function GetDeal(_deal_id){
    return new Promise(function(resolve, reject){
      var errors = [];
      var cache_id = $('.request_detail_edit').data('ajax_cache_id');
      
      var data = {
        cache_id: cache_id,
        session_id: BX.bitrix_sessid(),
        action: 'get_deal',
        deal_id: _deal_id,
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function UpdateDealLock(_deal_id){
    return new Promise(function(resolve, reject){
      var errors = [];
      var cache_id = $('.request_detail_edit').data('ajax_cache_id');
      
      var data = {
        cache_id: cache_id,
        session_id: BX.bitrix_sessid(),
        action: 'update_lock',
        deal_id: _deal_id,
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function Redial(_deal_id){
    return new Promise(function(resolve, reject){
      var errors = [];
      var cache_id = $('.request_detail_edit').data('ajax_cache_id');
      
      var data = {
        cache_id: cache_id,
        session_id: BX.bitrix_sessid(),
        action: 'redial',
        deal_id: _deal_id,
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function Hangup(){
    return new Promise(function(resolve, reject){
      var errors = [];
      var cache_id = $('.request_detail_edit').data('ajax_cache_id');
      
      var data = {
        cache_id: cache_id,
        session_id: BX.bitrix_sessid(),
        action: 'hangup',
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function UpdateDeal(_form){
    return new Promise(function(resolve, reject){
      var errors = [];
      var cache_id = $('.request_detail_edit').data('ajax_cache_id');
      
      var data = _form.serialize() + '&action=save_deal&cache_id='+ cache_id +'&session_id='+BX.bitrix_sessid();
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function GetNextInQueue(_group_ids){
    return new Promise(function(resolve, reject){
      var errors = [];
      var cache_id = $('.request_detail_edit').data('ajax_cache_id');

      var data = {
        cache_id: cache_id,
        session_id: BX.bitrix_sessid(),
        action: 'get_next',
        group_ids: _group_ids,
      };
      
      var settings = {
        url: "/local/components/whatasoft/ajax.util/ajax.php",
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
  
  function CheckConditionBlocks(_block){
    $('.conditional', _block).each(function(){
      var current_block = $(this);
      var condition = current_block.data('cond');
      var condition_value = current_block.data('val');
      if(condition){
        var condition_block = $('.conditional[data-id="'+condition+'"]', _block);
        if(condition_block.length){
          var select = $('select', condition_block);
          if(select.length){
            var val = select.val();
            if(val == condition_value){
              $('select, input, textarea', current_block).prop("disabled", false);
              current_block.show();
            }else{
              console.log($('select, input, textarea', current_block));
              $('select, input, textarea', current_block).prop("disabled", true);
              current_block.hide();
            }
          }
        }
      }
    });
  }
  
  function ShowMessageBlock(block, messageText){
    var htmlText = '<span>'+messageText+'</span>';
    block.html(htmlText);
    block.show();
  }
  
  function HideMessageBlock(block){
    block.html('');
    block.hide();
  }
  
  function ShowMessageErrorBlock(errorText) {
    var $block = $('#send-message-error-block');
    ShowMessageBlock($block, errorText);
  }
  
  function HideMessageErrorBlock() {
    var $block = $('#send-message-error-block');
    HideMessageBlock($block);
  }
  
  function ShowMessageSuccessBlock(successText) {
    var $block = $('#send-message-success-block');
    ShowMessageBlock($block, successText);
  }
  
  function HideMessageSuccessBlock() {
    var $block = $('#send-message-success-block');
    HideMessageBlock($block);
  }
  
  window.app = new Vue({
    el: '#v_app',
    data: {
      groups: [],
      deal_id: null,
      phone: null,
      queue_auto_call: false,
      queue_update_timer: null,
      queue_update_interval: 5 * 1000,
      queue_status_update_timer: null,
      queue_status_update_interval: 10 * 1000,
      deal_submit_timer: null,
      deal_submit_interval: 40 * 1000,
      deal_submit_show_timer: null,
      deal_submit_show_left: 0,
      deal_lock_update_interval: 60 * 1000,
      deal_lock_update_timer: null,
      queue_get_next_timer: null,
      queue_get_next_interval: 3 * 1000,
      call_delay: 1 * 1000,
      curSort: 'planned_call',
      curSortOrder: 'asc',
      filter: {
        group_ids: [],
      }
    },
    mounted: function(){
      this.telephony = window.telephony;
      this.jq_deal_block = $('#deal_block');
      this.queue_auto_call = $('.queue_block').data('auto_call') > 0;
      
      BX.addCustomEvent("onPullEvent-voximplant", BX.delegate(function(command,params){
        
        if(command == 'hideExternalCall'){
          setTimeout(function(){
            BX.rest.callMethod(
            'voximplant.statistic.get',
            {
              "FILTER": {"CALL_ID":params.callId},
            },
            function(result){
              if(result.error()){
                console.log(result.error());
              }else{
                if(result.answer.result.length){
                  if(result.answer.result[0].CALL_FAILED_CODE != '200'){
                    this.dealRedial();
                  }
                }
                console.log(result.answer.result[0].CALL_FAILED_CODE);
              }
            }.bind(this));
          }.bind(this), 1000);
          
          this.submitTimerStart();
        }
      }, this));
      
      this.showLoader();
      this.queueUpdate();
      this.queueGetNext();
      this.queueStatusUpdate();
    },
    methods: {
      clear: function(){
        this.filter.group_ids = [];
      },
      filterByGroup: function(row, index){
        return this.filter.group_ids.indexOf(row.id) !== -1;
      },
      sort: function(a,b){
        let modifier = 1;
        
        if(this.curSortOrder === 'desc'){
          modifier = -1;
        }
        if(a[this.curSort] < b[this.curSort]){
          return -1 * modifier;
        }
        if(a[this.curSort] > b[this.curSort]){
          return 1 * modifier;
        }
        return 0;
      },
      toggleGroup: function(_group_id){
        var ind = this.filter.group_ids.indexOf(_group_id);
        if(ind !== -1){
          this.filter.group_ids.splice(ind, 1);
        }else{
          this.filter.group_ids.push(_group_id);
        }
      },
      showLoader: function(){
        var loader = $(".queue_block .spinner_inline");
        loader.show();
      },
      hideLoader: function(){
        var loader = $(".queue_block .spinner_inline");
        loader.hide();
      },
      submitCountdownStart: function(){
        if(this.deal_submit_show_timer){
          clearInterval(this.deal_submit_show_timer);
        }
        
        this.deal_submit_show_left = Math.floor(this.deal_submit_interval / 1000);
        this.submitCountdownUpdate();
        this.deal_submit_show_timer = setInterval(function(){
          this.deal_submit_show_left--;
          if(this.deal_submit_show_left < 0){
            this.deal_submit_show_left = 0;
            this.submitCountdownStop();
          }
          this.submitCountdownUpdate();
        }.bind(this), 1000);
      },
      submitCountdownUpdate: function(){
        if($('.countdown', this.jq_deal_block).length){
          $('.countdown', this.jq_deal_block).removeClass('d-none');
          $('.countdown', this.jq_deal_block).html('До автосохранения: '+ this.deal_submit_show_left +' сек.');
        }
      },
      submitCountdownStop: function(){
        if(this.deal_submit_show_timer){
          clearInterval(this.deal_submit_show_timer);
        }
        if($('.countdown', this.jq_deal_block).length){
          $('.countdown', this.jq_deal_block).addClass('d-none');
        }
      },
      submitTimerStart: function(){
        if(this.deal_submit_timer){
          clearTimeout(this.deal_submit_timer);
        }
        if(this.deal_id == null){
          return;
        }
        this.deal_submit_timer = setTimeout(this.dealSubmit, this.deal_submit_interval);
        this.submitCountdownStart();
        this.dealHideHangupBtn();
      },
      submitTimerStop: function(){
        if(this.deal_submit_timer){
          clearTimeout(this.deal_submit_timer);
        }
        this.submitCountdownStop();
        this.dealLockTimerStop();
      },
      dealLockTimerStart: function(){
        if(this.deal_lock_update_timer){
          clearInterval(this.deal_lock_update_timer);
        }
        this.deal_lock_update_timer = setInterval(function(){
          UpdateDealLock(this.deal_id);
        }.bind(this), this.deal_lock_update_interval);
      },
      dealLockTimerStop: function(){
        if(this.deal_lock_update_timer){
          clearInterval(this.deal_lock_update_timer);
        }
      },
      queueStatusUpdate: function(){
        if(this.queue_status_update_timer){
          clearTimeout(this.queue_status_update_timer);
        }
        this.queue_status_update_timer = setTimeout(this.queueStatusUpdate, this.queue_status_update_interval);
        
        UpdateUserQueues(this.filter.group_ids).then(function(data){
          
        }.bind(this)).catch(function(errors){
          console.log(errors);
        }.bind(this));
      },
      queueUpdate: function(){
        if(this.queue_update_timer){
          clearTimeout(this.queue_update_timer);
        }
        this.queue_update_timer = setTimeout(this.queueUpdate, this.queue_update_interval);
        
        GetQueue().then(function(data){
          this.groups = data.groups;
          this.hideLoader();
        }.bind(this)).catch(function(errors){
          console.log(errors);
          this.hideLoader();
        }.bind(this));
      },
      updateDealCategory: function(category_id){
        UpdateDealCategory(this.deal_id, category_id).then(function(data){
          this.jq_deal_block.html(data.html);
          CheckConditionBlocks(this.jq_deal_block);
          $.magnificPopup.instance.updateItemHTML();
        }.bind(this)).catch(function(errors){
          console.log(errors);
        });
      },
      dealLoad: function(_id){
        GetDeal(_id).then(function(data){
          this.deal_id = data.deal_id;
          this.phone = data.phone;
          var deal_submit_interval = parseInt(data.submit_interval) || 40;
          this.deal_submit_interval = data.submit_interval * 1000;
          this.jq_deal_block.html(data.html);
          CheckConditionBlocks(this.jq_deal_block);
          
          $.magnificPopup.open({
            items: {
              src: this.jq_deal_block
            },
            
            type: 'inline',
            preloader: false,
            removalDelay: 300,
            mainClass: 'my-mfp-slide-bottom',
            closeBtnInside: true,
            midClick: true
          });
        }.bind(this)).catch(function(errors){
          console.log(errors);
        });
      },
      queueGetNextTimerUpdate: function(){
        if(!this.queue_auto_call){
          return;
        }
        
        if(this.queue_get_next_timer){
          clearTimeout(this.queue_get_next_timer);
        }
        
        this.queue_get_next_timer = setTimeout(this.queueGetNext, this.queue_get_next_interval);
      },
      queueGetNext: function(){
        if(this.filter.group_ids.length < 1){
          this.queueGetNextTimerUpdate();
          return;
        }
        
        GetNextInQueue(this.filter.group_ids).then(function(data){
          this.deal_id = data.deal_id;
          this.phone = data.phone;
          var deal_submit_interval = parseInt(data.submit_interval) || 40;
          this.deal_submit_interval = data.submit_interval * 1000;
          this.jq_deal_block.html(data.html);
          CheckConditionBlocks(this.jq_deal_block);
          
          /*покажем кнопку отключиться от всех*/
          $('.queue_block .btn.btn-danger').css('position', 'fixed').css('right', '0').css('top', '50px').css('z-index', '9999');
          
          $.magnificPopup.open({
            items: {
              src: this.jq_deal_block
            },
            
            type: 'inline',
            preloader: false,
            removalDelay: 300,
            mainClass: 'my-mfp-slide-bottom',
            modal: true,
            midClick: false
          });
          
          this.dealLockTimerStart();
          
          return this.delay(this.call_delay);
        }.bind(this)).then(function(data){
          this.makeCall(this.phone);
        }.bind(this)).catch(function(errors){
          console.log(errors);
          this.queueGetNextTimerUpdate();
        }.bind(this));
      },
      dealRedial: function(){
        var form = $('form', this.jq_deal_block);
        this.submitTimerStop();
        
        AddSpinner(form);
        Redial(this.deal_id).then(function(data){
          this.jq_deal_block.html('');
          this.deal_id = null;
          this.phone = null;
          $.magnificPopup.close();
          this.queueUpdate();
          this.queueGetNextTimerUpdate();
        }.bind(this)).catch(function(errors){
          console.log(errors);
          this.jq_deal_block.html('');
          this.deal_id = null;
          this.phone = null;
          $.magnificPopup.close();
          this.queueUpdate();
          this.queueGetNextTimerUpdate();
        }.bind(this));
      },
      dealHangup: function(){
        Hangup().then(function(data){
          
        }.bind(this)).catch(function(errors){
          console.log(errors);
        }.bind(this));
      },
      dealHideHangupBtn: function(){
        $('.hangup', this.jq_deal_block).hide();
      },
      dealSubmit: function(){
        var form = $('form', this.jq_deal_block);
        this.submitTimerStop();
        
        /*уберем кнопку отключиться от всех*/
        $('.queue_block .btn.btn-danger').css('position', 'initial');
        
        AddSpinner(form);
        UpdateDeal(form).then(function(data){
          this.jq_deal_block.html('');
          this.deal_id = null;
          this.phone = null;
          $.magnificPopup.close();
          this.queueUpdate();
          this.queueGetNextTimerUpdate();
        }.bind(this)).catch(function(errors){
          console.log(errors);
          this.jq_deal_block.html('');
          this.deal_id = null;
          this.phone = null;
          $.magnificPopup.close();
          this.queueUpdate();
          this.queueGetNextTimerUpdate();
        }.bind(this));
      },
      makeCall: function(_number){
        if(_number){
          this.telephony.phoneRest(_number);
        }
      },
      delay: function(_timeout){
        return new Promise(function(resolve, reject){
          setTimeout(function(){
            resolve(true);
          }, _timeout);
        });
      },
      sendSms: function(phone, templateId){
        HideMessageErrorBlock();
        HideMessageSuccessBlock();
        
        if (!phone) {
          ShowMessageErrorBlock('Не выбран номер получателя');
          return false;
        }
        
        if (!templateId) {
          ShowMessageErrorBlock('Не выбран шаблон SMS');
          return false;
        }
        
        SendSms(phone, templateId).then(function(data){
          ShowMessageSuccessBlock('Сообщение успешно отправлено!');
          $('#phone-value').val();
          $('#sms-template').val();
        }.bind(this)).catch(function(errors){
          ShowMessageErrorBlock(errors[0]);
        }.bind(this));
      },
      sendEmail: function(email, templateId){
        HideMessageErrorBlock();
        HideMessageSuccessBlock();
        
        if (!email) {
          ShowMessageErrorBlock('Не выбран Email получателя');
          return false;
        }
        
        if (!templateId) {
          ShowMessageErrorBlock('Не выбран шаблон Email');
          return false;
        }
        
        SendEmail(email, templateId).then(function(data){
          ShowMessageSuccessBlock('Сообщение успешно отправлено!');
          $('#email-value').val('');
          $('#email-template').val('');
        }.bind(this)).catch(function(errors){
          ShowMessageErrorBlock(errors[0]);
        }.bind(this));
      },
      sendCallTransferForm: function(action, data) {
        SendApiRequest(action, data).then(function(response){
          $('.call-transfer-block').hide();
          $('#call-transfer-user-id').val('');
          $('#call-transfer-phone').val('');
        }).catch(function(errors){
          console.log(errors);
        });
      }
    },
    computed:{
      filteredGroups: function(){
        return this.groups.filter(this.filterByGroup);
      },
      filteredItems: function(){
        var groups = this.filteredGroups;
        var arr = [];
        for(let i=0; i<groups.length; i++){
          arr = arr.concat(groups[i].items);
        }
        
        return arr;
      },
      sortedItems: function(){
        var items = this.filteredItems;
        
        return items.sort(this.sort);
      }
    }
  });
  
  $('.request_detail_edit').on('submit', 'form', function(){
    window.app.dealSubmit();
    
    return false;
  });
  
  $('.request_detail_edit').on('click', '.hangup', function(){
    window.app.dealHangup();
    
    return false;
  });
  
  $('.request_detail_edit').on('change', '.conditional select', function(){
    CheckConditionBlocks($('.request_detail_edit'));
  });
  
  // Смена категории при звонке
  $('.request_detail_edit').on('change', '#switch-category', function(){
    var $select = $(this);
    var categoryId = $select.val();
    window.app.updateDealCategory(categoryId);
    return false;
  });
  
  // Отправка SMS
  $('.request_detail_edit').on('click', '#send-sms-btn', function(){
    var phone = $('#phone-value').val();
    var templateId = $('#sms-template').val();
    window.app.sendSms(phone, templateId);
    return false;
  });
  
  // Отправка Email
  $('.request_detail_edit').on('click', '#send-email-btn', function(){
    var email = $('#email-value').val();
    var templateId = $('#email-template').val();
    window.app.sendEmail(email, templateId);
    return false;
  });
  
  // Перевод звонка
  $('.request_detail_edit').on('click', '#call-transfer-form-submit', function() {
    let $divForm = $('#call-transfer-form');
    let $fields = $divForm.find('input');
    let data = {};
    
    $.each($fields, function(key, _value) {
      let $element = $(_value);
      let name = $element.attr('name');
      let value = $element.val();
      data[name] = value;
    });
    
    let action = (data.CALL_TRANSFER_ACTION || '').trim();
    let user_id = (data.CALL_TRANSFER_USER_ID || '').replace( /^\D+/g, '');
    let phone = (data.CALL_TRANSFER_PHONE || '').trim();
    
    let params = {
      'USER_ID': user_id,
      'USER_PHONE': phone,
    };   
    
    if (action && (user_id || phone)) {
      window.app.sendCallTransferForm(action, params);
    }
    
  });
  
  // Сброс полей формы перевода звонка
  $('.request_detail_edit').on('click', '#call-transfer-form-reset', function() {
    let $divForm = $('#call-transfer-form');
    let $fields = $divForm.find('input');
    $.each($fields, function(key, _value) {
      let $element = $(_value);
      $element.val('');
    });
    // Очистка выбора пользователя
    $divForm.find('span[data-role="remove"]').click();
  });
  
  $('#uiToolbarContainer').append($('#markup-pagetitle-below'));
});
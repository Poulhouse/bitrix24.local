function reloadGrid()
{
  if (!window.grid_id) {
    return;
  }
  
  var reloadParams = { apply_filter: 'Y', clear_nav: 'Y' };
  var gridObject = BX.Main.gridManager.getById(window.grid_id);
  
  if (gridObject.hasOwnProperty('instance')){
    gridObject.instance.reloadTable('POST', reloadParams);
  }
}

function apiActionRequest(action, params)
{
  let apiUrl = location.href;
  let data = {
    session_id: BX.bitrix_sessid(),
    action: action,
  }
  
  params = Object.assign(data, params);
  
  HideError();
  httpPostPromise(apiUrl, params).then((response) => {
    reloadGrid();
  }).catch((errors) => {
    handleApiErrorResponse(errors);
  });
}

function handleApiErrorResponse(errors) {
  let firstError = errors.length ? errors[0] : 'Неизвестная ошибка';
  ShowError(firstError);
}

function switchCampaignStage(campaignId, stageCode)
{
  let params = {
    campaign_id: campaignId,
    stage: stageCode,
  };
  apiActionRequest('next_stage', params);
  return false;
}

function endCampaign(campaignId)
{
  if (!confirm('Завершить обработку кампании?')) {
    return false;
  }
  
  if (!window.END_CAMPAIGN_STAGE) {
    console.log('Не установлен код стадии завершения кампании');
    return false;
  }
  
  switchCampaignStage(campaignId, window.END_CAMPAIGN_STAGE);
}

function deleteCampaign(campaignId)
{
  if (!confirm('Удалить кампанию?')) {
    return false;
  }
    
  let params = {
    campaign_id: campaignId,
  };
  
  apiActionRequest('delete_campaign', params);
  return false;
}

function httpPostPromise(url, data) {
  return new Promise(function(resolve, reject){
    var errors = [];
    var settings = {
      url: url,
      type: "POST",
      cache: false,
      dataType: "json",
      data: data
    };
    
    var request = $.ajax(settings);
    
    request.done((response) => {
      if (response.wrong_session) {
        errors.push("Wrong session");
        reject(errors);
      }
      
      if (response.success) {
        resolve(response);
      }
      
      errors.push(response.message);
      reject(errors);
    });
    
    request.fail((jqXHR, textStatus) => {
      errors.push("Request failed: " + textStatus);
      reject(errors);
    });
  });
}

function getErrorBlock()
{
  return $('#errors');
}

function ShowError(error)
{
  let $block = getErrorBlock();
  $block.html(error);
  $block.show();
}

function HideError()
{
  getErrorBlock().hide();
}

$(document).ready(function(){
  
  $(document).on('click', '.switch-stage', function() {
    let $element = $(this);
    let campaignId = $element.data('id');
    let campaignStage = $element.data('stage');
    switchCampaignStage(campaignId, campaignStage);
    return false;
  });
  
});
$(document).ready(function(){
  function ApplyFilter(_params){
    return new Promise(function(resolve, reject){
      var errors = [];
      
      var settings = {
        url: "/crm/deal/category/0/?filter_id=CRM_DEAL_LIST_V12_C_0",
        type: "POST",
        cache: false,
        dataType: "html",
        data: _params
      };
      
      var request = $.ajax(settings);
      request.done(function(data){
        resolve(data);
      });
      
      request.fail(function(jqXHR, textStatus){
        errors.push("Request failed: " + textStatus);
        reject(errors);
      });
    });
  }
  
  $('.apply_filter').on('click', function(){
    ApplyFilter($(this).data('filter')).then(function(data){
      window.location.href = '/crm/deal/category/0/';
    }).catch(function(errors){
      
    });
    return false;
  });
});
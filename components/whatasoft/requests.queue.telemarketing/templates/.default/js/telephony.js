;(function(){
  // Объект телефонии
  const telephony = {
    // Совершает звонок на номер средствами Bitrix
    phoneToNumber: function(_number){
      this.getCallProvider().phoneTo(_number);
    },
    
    phoneRest: function(_number){
      BX.rest.callMethod(
        'voximplant.call.startViaRest',
        {
          'NUMBER': _number,
          'LINE_ID': this.getDefaultLineId(),
          'PARAMS': {},
          'SHOW': 'N'
        }
      ).then(function(data){
        //console.log(data.answer.result.DATA.CALL_ID);
        /*var test = BX.rest.callMethod(
          'voximplant.call.get',
          {
            'CALL_ID': data.answer.result.DATA.CALL_ID,
          }
        );
        console.log(test);*/
        //this.fold();
      }.bind(this));
    },
    
    fold: function(){
      if(this.getCallProvider().webrtc.phoneCallView){
        this.getCallProvider().webrtc.phoneCallView.fold();
      }
    },
    
    // Проверяет, активен ли текущий звонок
    isCurrentCallActive: function(){
      return this.getCallProvider().webrtc.callActive;
    },
    
    getCallProvider: function(){
      return window.BXIM;
    },
    
    getCallId: function(){
      return this.getCallProvider().webrtc.phoneCallId;
    },
    getDefaultLineId: function(){
      return this.getCallProvider().webrtc.phoneDefaultLineId;
    },
  };
  
  window.telephony = telephony;
})();
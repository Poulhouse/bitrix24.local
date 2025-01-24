BX.addCustomEvent(window, 'oncontrolchanged', function(){
    var $container2 = $('div[data-cid="ADDRESS"]'),
        $fieldsControlBlock = $container2.find('.location-fields-control-block'),
        $editorContentBlock = $fieldsControlBlock.find('.ui-entity-editor-content-block.ui-entity-editor-field-text');
        //$wrapper2 = $container2.find('.crm-address-search-control-block');
        //$editorContentBlock'.crm-address-control-item'
        //$addressOneString = $wrapper2.find('input');

    //$addressOneString.attr('did','addressOneString');

    if($editorContentBlock.length > 0){

        //console.log($editorContentBlock.length);

        for (var i = 0; i < $editorContentBlock.length; i++) {
            var $labelField = $editorContentBlock.find('label')[i];
            var $inputField = $editorContentBlock.find('input')[i];
            if($labelField.firstChild.data == 'Страна:') {
                $inputField.setAttribute('name','country');
                $inputField.setAttribute('value','Россия');
            }
            if($labelField.firstChild.data == 'Почтовый индекс:') {
                $inputField.setAttribute('name','zip');
                $inputField.setAttribute('autocomplete','false');
                //$('input[name="zip"]').prop('disabled', true);
            }
            if($labelField.firstChild.data == 'Регион:') {
                $inputField.setAttribute('name','region');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Район:') {
                $inputField.setAttribute('name','district');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Населенный пункт:') {
                $inputField.setAttribute('name','city');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Улица:') {
                $inputField.setAttribute('name','street');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Номер дома:') {
                $inputField.setAttribute('name','building');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Квартира, офис, комната, этаж:') {
                $inputField.setAttribute('name','other');
                $inputField.setAttribute('autocomplete','false');
            }
        }
    }
});
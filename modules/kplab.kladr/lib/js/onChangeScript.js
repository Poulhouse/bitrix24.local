BX.addCustomEvent(window, 'oncontrolchanged', function(){

    var $container1 = $('div[data-cid="ADDRESS"]'),
        $fieldsControlBlock1 = $container1.find('.location-fields-control-block'),
        $editorContentBlock1 = $fieldsControlBlock1.find('.ui-entity-editor-content-block.ui-entity-editor-field-text');
    var $container2 = $('div[data-cid="RQ_ADDR"]'),
        $fieldsControlBlock2 = $container2.find('.location-fields-control-block'),
        $editorContentBlock2 = $fieldsControlBlock2.find('.ui-entity-editor-content-block.ui-entity-editor-field-text');

    //$addressOneString.attr('did','addressOneString');

    if($editorContentBlock1.length > 0){

        //console.log($editorContentBlock.length);

        for (var i = 0; i < $editorContentBlock1.length; i++) {
            var $labelField1 = $editorContentBlock1.find('label')[i];
            var $inputField1 = $editorContentBlock1.find('input')[i];
            if($labelField1.firstChild.data == 'Страна:') {
                $inputField1.setAttribute('name','country');
                $inputField1.setAttribute('id','country');
                $inputField1.setAttribute('value','Россия');
                $inputField1.setAttribute('title','Россия');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Почтовый индекс:') {
                $inputField1.setAttribute('name','zip');
                $inputField1.setAttribute('id','zip');
                $inputField1.setAttribute('autocomplete','false');
                //$('input[name="zip"]').prop('disabled', true);
            }
            if($labelField1.firstChild.data == 'Регион:') {
                $inputField1.setAttribute('name','region');
                $inputField1.setAttribute('id','region');
                $inputField1.setAttribute('autocomplete','false');
                $regionValue1 = $inputField1.getAttribute('value');
            }
            if($labelField1.firstChild.data == 'Район:') {
                $inputField1.setAttribute('name','area');
                $inputField1.setAttribute('id','area');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Город:') {
                $inputField1.setAttribute('name','city');
                $inputField1.setAttribute('id','city');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Населенный пункт:') {
                $inputField1.setAttribute('name','settlement');
                $inputField1.setAttribute('id','settlement');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Улица:') {
                $inputField1.setAttribute('name','street');
                $inputField1.setAttribute('id','street');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Номер дома:') {
                $inputField1.setAttribute('name','house');
                $inputField1.setAttribute('id','house');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Участок:') {
                $inputField1.setAttribute('name','stead');
                $inputField1.setAttribute('id','stead');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Корпус:') {
                $inputField1.setAttribute('name','houseBlockK');
                $inputField1.setAttribute('id','houseBlockK');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Строение:') {
                $inputField1.setAttribute('name','houseBlockS');
                $inputField1.setAttribute('id','houseBlockS');
                $inputField1.setAttribute('autocomplete','false');
            }
            if ($labelField1.firstChild.data == 'Квартира/Помещение:') {
                $inputField1.setAttribute('name', 'flat');
                $inputField1.setAttribute('id', 'flat');
                $inputField1.setAttribute('autocomplete','false');
            }
            if ($labelField1.firstChild.data == 'Комната:') {
                $inputField1.setAttribute('name', 'room');
                $inputField1.setAttribute('id', 'room');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Офис, этаж, дополнительно:') {
                $inputField1.setAttribute('name','other');
                $inputField1.setAttribute('id','other');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'ФИАС ID:') {
                $inputField1.setAttribute('name','fiasId');
                $inputField1.setAttribute('id','fiasId');
                $inputField1.setAttribute('autocomplete','false');
                $inputField1.setAttribute('disabled','true');
            }
        }
    }
    if($editorContentBlock2.length > 0){

        //console.log($editorContentBlock.length);

        for (var j = 0; j < $editorContentBlock2.length; j++) {
            var $labelField = $editorContentBlock2.find('label')[j];
            var $inputField = $editorContentBlock2.find('input')[j];
            if($labelField.firstChild.data == 'Страна:') {
                $inputField.setAttribute('name','country');
                $inputField.setAttribute('id','country');
                $inputField.setAttribute('value','Россия');
                $inputField.setAttribute('title','Россия');
                $inputField.setAttribute('autocomplete','false');
                const event = new Event('change', { bubbles: true, cancelable: true });
                //$inputField.dispatchEvent(event);
            }
            if($labelField.firstChild.data == 'Почтовый индекс:') {
                $inputField.setAttribute('name','zip');
                $inputField.setAttribute('id','zip');
                $inputField.setAttribute('autocomplete','false');
                //$('input[name="zip"]').prop('disabled', true);
            }
            if($labelField.firstChild.data == 'Регион:') {
                $inputField.setAttribute('name','region');
                $inputField.setAttribute('id','region');
                $inputField.setAttribute('autocomplete','false');
                $regionValue = $inputField.getAttribute('value');
            }
            if($labelField.firstChild.data == 'Район:') {
                $inputField.setAttribute('name','area');
                $inputField.setAttribute('id','area');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Город:') {
                $inputField.setAttribute('name','city');
                $inputField.setAttribute('id','city');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Населенный пункт:') {
                $inputField.setAttribute('name','settlement');
                $inputField.setAttribute('id','settlement');
                $inputField.setAttribute('autocomplete','false');
                /*$cityValue = $inputField.getAttribute('value');
                if($regionValue !== "" && $regionValue == $cityValue) {
                    // Скрываем родительский элемент
                    const parentElement = $inputField.closest('.ui-entity-editor-content-block.ui-entity-editor-field-text'); // Или используйте ближайший подходящий класс
                    if (parentElement) {
                        parentElement.style.display = 'none';
                    }
                }*/
            }
            if($labelField.firstChild.data == 'Улица:') {
                $inputField.setAttribute('name','street');
                $inputField.setAttribute('id','street');
                $inputField.setAttribute('autocomplete','false');
            }

            if($labelField.firstChild.data == 'Номер дома:') {
                $inputField.setAttribute('name','house');
                $inputField.setAttribute('id','house');
                $inputField.setAttribute('autocomplete','false');
            }

            if($labelField.firstChild.data == 'Участок:') {
                $inputField.setAttribute('name','stead');
                $inputField.setAttribute('id','stead');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Корпус:') {
                $inputField.setAttribute('name','houseBlockK');
                $inputField.setAttribute('id','houseBlockK');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Строение:') {
                $inputField.setAttribute('name','houseBlockS');
                $inputField.setAttribute('id','houseBlockS');
                $inputField.setAttribute('autocomplete','false');
            }

            if ($labelField.firstChild.data == 'Квартира/Помещение:') {
                $inputField.setAttribute('name', 'flat');
                $inputField.setAttribute('id', 'flat');
                $inputField.setAttribute('autocomplete','false');
            }
            if ($labelField.firstChild.data == 'Комната:') {
                $inputField.setAttribute('name', 'room');
                $inputField.setAttribute('id', 'room');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Офис, этаж, дополнительно:') {
                $inputField.setAttribute('name','other');
                $inputField.setAttribute('id','other');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'ФИАС ID:') {
                $inputField.setAttribute('name','fiasId');
                $inputField.setAttribute('id','fiasId');
                $inputField.setAttribute('autocomplete','false');
                $inputField.setAttribute('disabled','true');
            }
        }
    }


});
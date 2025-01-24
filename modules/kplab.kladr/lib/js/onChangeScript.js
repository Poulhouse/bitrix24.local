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
            if($labelField1.firstChild.data == 'Населенный пункт:') {
                $inputField1.setAttribute('name','city-settlement');
                $inputField1.setAttribute('id','city-settlement');
                $inputField1.setAttribute('autocomplete','false');
                $cityValue1 = $inputField1.getAttribute('value');
                if($regionValue1 !== "" && $regionValue1 == $cityValue1) {
                    // Скрываем родительский элемент
                    const parentElement = $inputField1.closest('.ui-entity-editor-content-block.ui-entity-editor-field-text'); // Или используйте ближайший подходящий класс
                    if (parentElement) {
                        parentElement.style.display = 'none';
                    }
                }
            }
            if($labelField1.firstChild.data == 'Улица:') {
                $inputField1.setAttribute('name','street');
                $inputField1.setAttribute('id','street');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Улица, номер дома:') {
                $inputField1.setAttribute('name','streetHouse');
                $inputField1.setAttribute('id','streetHouse');
                $inputField1.setAttribute('autocomplete','false');
                $inputField1.setAttribute('disabled','true');
                // Скрываем родительский элемент
                const parentElement = $inputField1.closest('.ui-entity-editor-content-block.ui-entity-editor-field-text'); // Или используйте ближайший подходящий класс
                if (parentElement) {
                    parentElement.style.display = 'none';
                }
            }
            if($labelField1.firstChild.data == 'Номер дома:') {
                $inputField1.setAttribute('name','house');
                $inputField1.setAttribute('id','house');
                $inputField1.setAttribute('autocomplete','false');
            }
            if($labelField1.firstChild.data == 'Квартира, офис, комната, этаж:') {
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
            if($labelField.firstChild.data == 'Населенный пункт:') {
                $inputField.setAttribute('name','city-settlement');
                $inputField.setAttribute('id','city-settlement');
                $inputField.setAttribute('autocomplete','false');
                $cityValue = $inputField.getAttribute('value');
                if($regionValue !== "" && $regionValue == $cityValue) {
                    // Скрываем родительский элемент
                    const parentElement = $inputField.closest('.ui-entity-editor-content-block.ui-entity-editor-field-text'); // Или используйте ближайший подходящий класс
                    if (parentElement) {
                        parentElement.style.display = 'none';
                    }
                }
            }
            if($labelField.firstChild.data == 'Улица:') {
                $inputField.setAttribute('name','street');
                $inputField.setAttribute('id','street');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Улица, номер дома:') {
                $inputField.setAttribute('name','streetHouse');
                $inputField.setAttribute('id','streetHouse');
                $inputField.setAttribute('autocomplete','false');
                $inputField.setAttribute('disabled','true');
                // Скрываем родительский элемент
                const parentElement = $inputField.closest('.ui-entity-editor-content-block.ui-entity-editor-field-text'); // Или используйте ближайший подходящий класс
                if (parentElement) {
                    parentElement.style.display = 'none';
                }
            }
            if($labelField.firstChild.data == 'Номер дома:') {
                $inputField.setAttribute('name','house');
                $inputField.setAttribute('id','house');
                $inputField.setAttribute('autocomplete','false');
            }
            if($labelField.firstChild.data == 'Квартира, офис, комната, этаж:') {
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
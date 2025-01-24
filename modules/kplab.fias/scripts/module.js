BX.addCustomEvent('onChangeAddress',function () {
	console.log('!!!');
	var $container2 = $('div[data-cid="ADDRESS"]'),
		$fieldsControlBlock = $container2.find('.location-fields-control-block'),
		$editorContentBlock = $fieldsControlBlock.find('.ui-entity-editor-content-block.ui-entity-editor-field-text'),
		$wrapper2 = $container2.find('.crm-address-search-control-block'),
		$addressOneString = $wrapper2.find('input');

    $addressOneString.data('id','addressOneString');

    if($editorContentBlock.length > 0){

        console.log($editorContentBlock.length);

        for (var i = 0; i < $editorContentBlock.length; i++) {
            var $labelField = $editorContentBlock.find('label')[i];
            var $inputField = $editorContentBlock.find('input')[i];
            if($labelField.firstChild.data == 'Почтовый индекс:') {
                $inputField.id = 'zip';
                $('#zip').prop('disabled', true);
            }
            if($labelField.firstChild.data == 'Регион:') {
                $inputField.id = 'region';
            }
            if($labelField.firstChild.data == 'Район:') {
                $inputField.id = 'district';
            }
            if($labelField.firstChild.data == 'Населенный пункт:') {
                $inputField.id = 'city';
            }
            if($labelField.firstChild.data == 'Улица, номер дома:') {
                $inputField.id = 'street';
            }
            if($labelField.firstChild.data == 'Квартира, офис, комната, этаж:') {
                $inputField.id = 'building';
            }
        }
    }
});

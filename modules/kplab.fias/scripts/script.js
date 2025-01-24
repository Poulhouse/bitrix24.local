BX.addCustomEvent(window, 'oncontrolchanged', BX.delegate(function(command,params){

		var $container1 = $('div[data-cid="ADDRESS"]'),
			$wrapper = $container1.find('.crm-address-search-control-block'),
			$addressOneString = $wrapper.find('input'),
			$districtType = '';

		var name='',type='',zip='',$zipCode='';

		$('input[type="text"]').on('input', function (e) {
			e.preventDefault();

			var $zip;
			var $titleAddress = $(this).parents('div[data-cid="ADDRESS"]').find('.ui-entity-editor-block-title.ui-entity-widget-content-block-title-edit');
			if ($titleAddress.find('label').text() == "Адрес") {
				$address = $titleAddress.parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input');
				//$address.attr('didi','currentAddress');
				$address.fias({
					oneString: true,
					check: function (obj) {
					},
					change: function (obj) {
						console.log(obj);
						setFullAddress(obj);
					}
				});
			}
			if ($(this).parent().parent().parent().find('label').text() == 'Страна:') {
				//setValueInput($(this),'Россия');
			}
			if ($(this).parent().parent().parent().find('label').text() == 'Почтовый индекс:') {
				$zip = $(this);
				//$zip.prop('disabled', true);
				//$zip.prop('id', 'zipcode');
			}
			if ($(this).parent().parent().parent().find('label').text() == 'Регион:') {
				$region = $(this);

				$region.fias({
					verify: true,
					type: 'region',
					check: function (obj) {
						if (obj) {
							//setLabel($region, obj.type);
						}
					},
					change: function (obj) {
						console.log(obj.type);
						$regionType = 'region';
						$regionid = obj.id;
						$regionObj = obj;
						$('input[name="region"]').attr('value',$regionObj.name + ' ' + $regionObj.typeShort);

					}
				});
			}
			if ($(this).parent().parent().parent().find('label').text() == 'Район:') {
				$district = $(this);

				$district.fias({
					verify: true,
					type: 'district',
					parentType: $regionType,
					parentId: $regionid,
					withParents: true,
					check: function (obj) {
						if (obj) {
							//setLabel($district, obj.type);
						}
					},
					change: function (obj) {
						console.log(obj.type);
						$districtType = 'district';
						$districtid = obj.id;
						$districtObj = obj;
						$('input[name="district"]').attr('value',$districtObj.name + ' ' + $districtObj.typeShort);
					}
				});
			}
			if ($(this).parent().parent().parent().find('label').text() == 'Населенный пункт:') {
				$city = $(this);
				if ($districtType !== 'district') {
					$parentType = $regionType
					$parentId = $regionid
				} else {
					$parentType = 'district'
					$parentId = $districtid
				}
				$city.fias({
					verify: true,
					type: 'city',
					withParents: true,
					parentType: $parentType,
					parentId: $parentId,
					check: function (obj) {
						if (obj) {
							//setLabel($city, obj.type);
						}
					},
					change: function (obj) {
						console.log(obj.type);
						$cityType = 'city';
						$cityid = obj.id;
						$cityObj = obj;
						$('input[name="city"]').attr('value',$cityObj.name + ' ' + $cityObj.typeShort);
					}
				});
			}
			if ($(this).parent().parent().parent().find('label').text() == 'Улица, номер дома:' || $(this).parent().parent().parent().find('label').text() == 'Улица:') {
				$(this).parent().parent().parent().find('label').text('Улица:');
				$street = $(this);

				$street.fias({
					verify: true,
					type: 'street',
					parentType: 'city',
					parentId: $cityid,
					withParents: true,
					check: function (obj) {
						if (obj) {
							//setLabel($street, obj.type);
						}
					},
					change: function (obj) {
						console.log(obj.type);
						$streetType = 'street';
						$streetid = obj.id;
						$zipCode_1 = obj.zip;
						setZip($('#zip'), $zipCode_1);
						$streetObj = obj;
						$('input[name="street"]').attr('value',$streetObj.name + ' ' + $streetObj.typeShort);
					}
				});
			}
			if ($(this).parent().parent().parent().find('label').text() == 'Номер дома:') {
				$building = $(this);

				$building.fias({
					verify: true,
					type: 'building',
					parentType: $streetType,
					parentId: $streetid,
					withParents: true,
					check: function (obj) {
						if (obj) {
							//setLabel($building, obj.type);
						}
					},
					change: function (obj) {
						console.log(obj);
						$zipCode = obj.zip;
						$buildingObj = obj;
						$('input[name="building"]').attr('value',$buildingObj.name + ' ' + $buildingObj.typeShort);
						setZip($('input[name="zip"]'), $zipCode);
					}
				});
			}
			if ($(this).parent().parent().parent().find('label').text() == 'Квартира, офис, комната, этаж:') {
				$other = $(this);
			}

		});

	$('input[type="text"]').on('focusout', function(e) {
		e.preventDefault();

		var $zip;

		if ($(this).parent().parent().parent().find('label').text() == 'Страна:') {
			//setValueInput($(this),'Россия');
		}
		if ($(this).parent().parent().parent().find('label').text() == 'Почтовый индекс:') {
			$zip = $(this);
			//$zip.prop('disabled', true);
			//$zip.prop('id', 'zipcode');
		}
		if ($(this).parent().parent().parent().find('label').text() == 'Регион:') {
			$region = $(this);
			e.preventDefault();
			setFull($region);
		}
		if ($(this).parent().parent().parent().find('label').text() == 'Район:') {
			$district = $(this);
			setFull($district);
		}
		if ($(this).parent().parent().parent().find('label').text() == 'Населенный пункт:') {
			$city = $(this);
			setFull($city);
		}
		if ($(this).parent().parent().parent().find('label').text() == 'Улица, номер дома:' || $(this).parent().parent().parent().find('label').text() == 'Улица:') {
			$(this).parent().parent().parent().find('label').text('Улица:');
			$street = $(this);
			setFull($street);
		}
		if ($(this).parent().parent().parent().find('label').text() == 'Номер дома:') {
			$building = $(this);
			setFull($building);
		}
		if ($(this).parent().parent().parent().find('label').text() == 'Квартира, офис, комната, этаж:') {
			$other = $(this);
			setFull($other);
		}

	});




		/*$('input[name="region"]').on('blur', function (e) {
			name = $regionObj.name;
			type = $regionObj.typeShort;
			zip = $regionObj.zip || zip;

			$regionAddress = name + ' ' + type;
			address = (zip ? zip + ', ' : '') + ($regionAddress ? $regionAddress + ', ' : '') + ',' + (districtAddress ? districtAddress + ', ' : '') + ',' + (cityAddress ? cityAddress + ', ' : '')  + ',' + (streetAddress ? streetAddress + ', ' : '') + ',' + (buildingAddress ? buildingAddress : '');
			console.log(address);
			//setValueInput($(this).parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input'), address);

		});*/
		/*$('input[name="district"]').on('blur', function (e) {
			name = $districtObj.name;
			type = $districtObj.typeShort;
			zip = $districtObj.zip || zip;

			districtAddress = name + ' ' + type;
			address = (zip ? zip + ', ' : '') + ($regionAddress ? $regionAddress + ', ' : '') + ',' + (districtAddress ? districtAddress + ', ' : '') + ',' + (cityAddress ? cityAddress + ', ' : '')  + ',' + (streetAddress ? streetAddress + ', ' : '') + ',' + (buildingAddress ? buildingAddress : '');
			console.log(address);
			//setValueInput($(this).parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input'), address);
		});*/
		/*$('input[name="city"]').on('blur', function (e) {
			name = $cityObj.name;
			type = $cityObj.typeShort;
			zip = $cityObj.zip || zip;

			cityAddress = name + ' ' + type;
			address = (zip ? zip + ', ' : '') + ($regionAddress ? $regionAddress + ', ' : '') + ',' + (districtAddress ? districtAddress + ', ' : '') + ',' + (cityAddress ? cityAddress + ', ' : '')  + ',' + (streetAddress ? streetAddress + ', ' : '') + ',' + (buildingAddress ? buildingAddress : '');
			console.log(address);
			//setValueInput($(this).parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input'), address);
		});*/
		/*$('input[name="street"]').on('blur', function (e) {
			name = $streetObj.name;
			type = $streetObj.typeShort;
			zip = $streetObj.zip || zip;
			streetAddress = name + ' ' + type;
			address = (zip ? zip + ', ' : '') + ($regionAddress ? $regionAddress + ', ' : '') + ',' + (districtAddress ? districtAddress + ', ' : '') + ',' + (cityAddress ? cityAddress + ', ' : '')  + ',' + (streetAddress ? streetAddress + ', ' : '') + ',' + (buildingAddress ? buildingAddress : '');
			console.log(address);
			//setValueInput($(this).parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input'), address);
		});*/
		/*$('input[name="building"]').on('blur', function (e) {
			name = $buildingObj.name;
			type = $buildingObj.typeShort;
			zip = $buildingObj.zip || zip;
			//buildingAddress = name + ' ' + type;
			//address = (zip ? zip + ', ' : '') + ($regionAddress ? $regionAddress + ', ' : '') + ',' +
			// (districtAddress ? districtAddress + ', ' : '') + ',' + (cityAddress ? cityAddress + ', ' : '')  + ',' + (streetAddress ? streetAddress + ', ' : '') + ',' + (buildingAddress ? buildingAddress : '');
			//console.log(address);
			//setValueInput($(this).parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input'), address);
		});*/

}));

function setZip($input, $code) {
	$input.val($code);
}

function setValueInput(input, text) {
	$(input).val(text);
}

function setLabel($input, text) {
	text = text.charAt(0).toUpperCase() + text.substr(1).toLowerCase();
	console.log(text);
	$input.parent().parent().parent().find('label').text(text);
}

function setFullAddress(obj) {
	var $textBuild = obj.name + ' ' + obj.typeShort;
	var $idBuild = '#' + obj.contentType;

	$addressFullInput = $($idBuild).parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input');

	//console.log('input[name="' + obj.parents[0].contentType+'"]', obj.parents[0].name + ' ' + obj.parents[0].typeShort, 'Если #building');

	if ($idBuild == '#building') {

		setValueInput('input[name="building"]', $textBuild);

		for (var i = 0; i < obj.parents.length; i++) {
			var $idElem = 'input[name="' + obj.parents[i].contentType+'"]';
			setValueInput($idElem, obj.parents[i].name + ' ' + obj.parents[i].typeShort);
		}
	}
}

function setFull($this) {
	$country = $('input[name="country"]').val();
	$region = $('input[name="region"]').val();
	$district = $('input[name="district"]').val();
	$city = $('input[name="city"]').val();
	$street = $('input[name="street"]').val();
	$building = $('input[name="building"]').val();
	$other = $('input[name="other"]').val();
	$zip = $('input[name="zip"]').val();

/*
	if($zip !== '' && $other == '') $fullAddress = $zip +', '+$country+', '+$region+', '+$district+', '+$city+', ' + $street + ', ' + $building;
	if($zip == '' && $other == '') $fullAddress = $country + ', '+ $region + ', ' + $district + ', ' + $city + ', ' + $street + ', ' + $building;
	if($zip !== '' && $other !== '') */

	$fullAddress = $zip +', '+$country+', '+$region+', '+$district+', '+$city+', ' + $street + ', ' + $building + ', ' + $other;
	$addressFullInput = $this.parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input');

	$addressFullInput.val($fullAddress);
	$addressFullInput.setAttribute('value', $fullAddress);

	console.log($fullAddress);
}

/*
function excludeKladr($field) {
	$field.removeAttr('data-kladr-type');
	$field.removeAttr('data-kladr-id');
}
*/

function includeKladr($field) {

}
let currentController;
async function fetchSuggestions(query, token, bounds, constraints) {
    if (currentController) currentController.abort(); // Отменяем предыдущий запрос
    currentController = new AbortController();

    try {
        const requestData = {
            query: query,
            from_bound: { value: bounds },
            to_bound: { value: bounds },
            restrict_value: true,
            locations: constraints
        };

        const response = await fetch("https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": "Token " + token
            },
            body: JSON.stringify(requestData),
            signal: currentController.signal
        });
        const data = await response.json();
        return data.suggestions || [];
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error("Ошибка запроса:", error);
        }
        return [];
    }
}

BX.ready(function () {

    BX.addCustomEvent(window, 'oncontrolchanged', BX.delegate(function (command, params) {

        const type = "ADDRESS";
        const token = "440b60bed73f6e0d78a0eb09ca91971f8c079590";
        let selectedRegion = null;
        let selectedArea = null;
        let selectedCitySettlement = null;
        let selectedStreet = null;
        let selectedHouse = null;
        let selectedHouseFlat = null;
        let regionValue = "";
        let areaValue = "";
        let selectedCitySettlementValue = "";
        let streetValue = "";
        let houseValue = "";
        let houseFlatValue = "";
        let zipValue = "";
        let fiasIdValue = "";
        let blockValue = "";
        let houseBlockValue = "";

        document.querySelectorAll('.crm-address-control-item').forEach((addressBlock) => {
            const typeElement = addressBlock.querySelector('.ui-ctl-element[title]');
            const addressType = typeElement ? typeElement.textContent.trim() : 'Неизвестный тип';

            addressBlock.querySelectorAll('input.ui-ctl-element').forEach((input) => {
                input.addEventListener('input', debounce(async (event) => {

                    let lastQuery = ''; // Последний выполненный запрос
                    let lastSuggestions = []; // Последний список подсказок
                    let debounceTimeout; // Таймер для дебаунса

                    const query = event.target.value;

                    if (query === lastQuery) return;
                    lastQuery = query;

                    if(input.name == 'region') {
                        try {
                            const suggestions = await fetchSuggestions(query, token, "region", []);
                            showSuggestions($(input), suggestions, (selected) => {
                                selectedRegion = selected.data;
                                regionValue = selectedRegion.region + ' ' + selectedRegion.region_type;
                                fiasIdValue = selectedRegion.fias_id;
                                countryValue = selectedRegion.country;

                                BX.adjust(input, {
                                    props: { value: regionValue }
                                });
                                console.log("Регион выбран:", selectedRegion);

                                // Триггерим событие change
                                const event = new Event('change', { bubbles: true, cancelable: true });
                                input.dispatchEvent(event);

                                const $fiasId = $(input).closest('.crm-address-control-item').find('input[name="fiasId"]');
                                updateFiasId($fiasId,fiasIdValue);

                                const $country = $(input).closest('.crm-address-control-item').find('input[name="country"]');
                                updateCountry($country,countryValue);



                                if(selectedRegion.city == "Москва") {
                                    selectedCitySettlement = selectedRegion;
                                    selectedCitySettlementValue = selectedCitySettlement.city + ' ' + selectedCitySettlement.city_type;
                                    fiasIdValue = selectedCitySettlement.city_fias_id;

                                    const $citySettlement = $(input).closest('.crm-address-control-item').find('input[name="city-settlement"]');
                                    updateCityField($citySettlement,selectedCitySettlementValue);

                                    // Скрываем родительский элемент
                                    const parentElement = $citySettlement.closest('.ui-entity-editor-content-block.ui-entity-editor-field-text'); // Или используйте ближайший подходящий класс
                                    if (parentElement) {
                                        $(parentElement).hide();
                                    }

                                    const $fiasId = $(input).closest('.crm-address-control-item').find('input[name="fiasId"]');
                                    updateFiasId($fiasId,fiasIdValue);
                                }

                            });
                        } catch (error) {
                            console.error("Ошибка получения подсказок:", error);
                        }
                    }
                    if(input.name == 'area') {
                        try {
                            const suggestions = await fetchSuggestions(query, token, "area", [
                                {region_fias_id: selectedRegion.fias_id}
                            ]);
                            showSuggestions($(input), suggestions, (selected) => {
                                selectedArea = selected.data;
                                areaValue = selectedArea.area + " " + selectedArea.area_type;
                                fiasIdValue = selectedArea.fias_id;

                                BX.adjust(input, {
                                    props: { value: areaValue },
                                    attrs: { title: areaValue }
                                });
                                // Триггерим событие change
                                const event = new Event('change', { bubbles: true, cancelable: true });
                                input.dispatchEvent(event);

                                const $fiasId = $(input).closest('.crm-address-control-item').find('input[name="fiasId"]');
                                updateFiasId($fiasId,fiasIdValue);

                                console.log("Район выбран:", selectedArea);
                            });
                        } catch (error) {
                            console.error("Ошибка получения подсказок:", error);
                        }
                    }
                    if(input.name == 'city-settlement') {
                        try {
                            const suggestions = await fetchSuggestions(query, token, "city-settlement", [
                                {region_fias_id: selectedRegion.fias_id},
                                ...(selectedArea ? [{area_fias_id: selectedArea.fias_id}] : [])
                            ]);

                            showSuggestions($(input), suggestions, (selected) => {
                                selectedCitySettlement = selected.data;
                                selectedCitySettlementValue = selectedCitySettlement.settlement_fias_id
                                    ? selectedCitySettlement.settlement + " " + selectedCitySettlement.settlement_type
                                    : selectedCitySettlement.city + " " + selectedCitySettlement.city_type;
                                fiasIdValue = selectedCitySettlement.fias_id;

                                BX.adjust(input, {
                                    props: { value: selectedCitySettlementValue },
                                    attrs: { title: selectedCitySettlementValue }
                                });

                                // Триггерим событие change
                                const event = new Event('change', { bubbles: true, cancelable: true });
                                input.dispatchEvent(event);

                                const $fiasId = $(input).closest('.crm-address-control-item').find('input[name="fiasId"]');
                                updateFiasId($fiasId,fiasIdValue);

                                console.log("Населенный пункт выбран:", selectedCitySettlement);
                            });
                        } catch (error) {
                            console.error("Ошибка получения подсказок:", error);
                        }
                    }
                    if(input.name == 'street') {
                        try {
                            // Определяем, какой ID использовать
                            const locationConstraint = selectedCitySettlement.settlement_fias_id
                                ? {settlement_fias_id: selectedCitySettlement.settlement_fias_id}
                                : {city_fias_id: selectedCitySettlement.city_fias_id};

                            const suggestions = await fetchSuggestions(query, token, "street", [locationConstraint]);

                            showSuggestions($(input), suggestions, (selected) => {
                                selectedStreet = selected.data;
                                streetValue = selectedStreet.street + " " + selectedStreet.street_type;
                                fiasIdValue = selectedStreet.fias_id;

                                //setValueInput($street, streetValue);

                                BX.adjust(input, {
                                    props: { value: streetValue },
                                    attrs: { title: streetValue }
                                });

                                // Триггерим событие change
                                const event = new Event('change', { bubbles: true, cancelable: true });
                                input.dispatchEvent(event);

                                const $streetHouse = $(input).closest('.crm-address-control-item').find('input[name="streetHouse"]');
                                updateStreetHouse($streetHouse, streetValue, houseValue);

                                const $fiasId = $(input).closest('.crm-address-control-item').find('input[name="fiasId"]');
                                updateFiasId($fiasId,fiasIdValue);

                                console.log("Улица выбрана:", selectedStreet);
                            });
                        } catch (error) {
                            console.error("Ошибка получения подсказок:", error);
                        }
                    }
                    if(input.name == 'house') {
                        try {
                            const suggestions = await fetchSuggestions(query, token, "house", [
                                {street_fias_id: selectedStreet.fias_id}
                            ]);

                            showSuggestions($(input), suggestions, (selected) => {
                                selectedHouse = selected.data;
                                houseValue = selectedHouse.house + " " + selectedHouse.house_type;
                                zipValue = selectedHouse.postal_code;
                                fiasIdValue = selectedHouse.fias_id;
                                if(selectedHouse.block_type_full) {
                                    blockValue = selectedHouse.block_type_full + " " + selectedHouse.block;
                                    houseBlockValue = selectedHouse.house + " " + blockValue;
                                    houseValue = houseBlockValue + " " + selectedHouse.house_type;
                                }

                                //$house.value = houseValue;
                                //setValueInput($house, houseValue);

                                BX.adjust(input, {
                                    props: { value: houseValue },
                                    attrs: { title: houseValue }
                                });

                                // Триггерим событие change
                                const event = new Event('change', { bubbles: true, cancelable: true });
                                input.dispatchEvent(event);

                                $streetHouse = $(input).closest('.crm-address-control-item').find('input[name="streetHouse"]');
                                updateStreetHouse($streetHouse, streetValue, houseValue);

                                $zip = $(input).closest('.crm-address-control-item').find('input[name="zip"]');
                                BX.adjust($zip[0], {
                                    props: { value: zipValue },
                                    attrs: { title: zipValue }
                                });

                                const event2 = new Event('change', { bubbles: true, cancelable: true });
                                $zip[0].dispatchEvent(event2);

                                const $fiasId = $(input).closest('.crm-address-control-item').find('input[name="fiasId"]');
                                updateFiasId($fiasId,fiasIdValue);

                                console.log("Дом выбран:", selected);
                            });
                        } catch (error) {
                            console.error("Ошибка получения подсказок:", error);
                        }
                    }
                    if(input.name == 'other') {
                        try {
                            const suggestions = await fetchSuggestions(houseValue + ", кв." + query, token, "house-flat", [
                                {street_fias_id: selectedStreet.fias_id}
                            ]);
                            showSuggestions($(input), suggestions, (selected) => {
                                selectedHouseFlat = selected.data;
                                houseFlatValue = selectedHouseFlat.flat + " " +selectedHouseFlat.flat_type;
                                fiasIdValue = selectedHouseFlat.fias_id;

                                BX.adjust(input, {
                                    props: { value: houseFlatValue },
                                    attrs: { title: houseFlatValue }
                                });
                                // Триггерим событие change
                                const event = new Event('change', { bubbles: true, cancelable: true });
                                input.dispatchEvent(event);

                                const $fiasId = $(input).closest('.crm-address-control-item').find('input[name="fiasId"]');
                                updateFiasId($fiasId,fiasIdValue);

                                console.log("квартира выбрана:", selected);
                            });
                        } catch (error) {
                            console.error("Ошибка получения подсказок:", error);
                        }
                    }
                }, 600));
            });
        });

        // Функция для обновления streetHouse
        function updateStreetHouse($streetHouse, streetValue, houseValue) {
            const combinedValue = (streetValue ? streetValue : "") + (houseValue ? ", " + houseValue : "");
            BX.adjust($streetHouse[0], {
                props: { value: combinedValue.trim() },
                attrs: { title: combinedValue.trim() }
            });

            // Триггерим событие change
            const event = new Event('change', { bubbles: true, cancelable: true });
            $streetHouse[0].dispatchEvent(event);

            console.log("Обновлено streetHouse:", combinedValue);
        }

        // Функция для обновления country
        function updateCountry($country, countryValue) {
            BX.adjust($country[0], {
                props: { value: countryValue.trim() },
                attrs: { title: countryValue.trim() }
            });

            // Триггерим событие change
            const event = new Event('change', { bubbles: true, cancelable: true });
            $country[0].dispatchEvent(event);

            console.log("Обновлено country:", countryValue);
        }

        // Функция для обновления fiasId
        function updateFiasId($fiasId, fiasIdValue) {
            BX.adjust($fiasId[0], {
                props: { value: fiasIdValue.trim() },
                attrs: { title: fiasIdValue.trim() }
            });

            // Триггерим событие change
            const event = new Event('change', { bubbles: true, cancelable: true });
            $fiasId[0].dispatchEvent(event);

            console.log("Обновлено fiasId:", fiasIdValue);
        }
        // Функция для обновления city
        function updateCityField($city, cityValue) {
            BX.adjust($city[0], {
                props: { value: cityValue.trim() },
                attrs: { title: cityValue.trim() }
            });

            // Триггерим событие change
            const event = new Event('change', { bubbles: true, cancelable: true });
            $city[0].dispatchEvent(event);

            console.log("Обновлено city:", cityValue);
        }

        function debounce(func, delay) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), delay);
            };
        }

        // Функция отображения подсказок
        function showSuggestions($inputElement, suggestions, onSelect) {
            // Оборачиваем в jQuery, если передан нативный элемент
            $inputElement = $($inputElement);

            // Удаляем предыдущие подсказки, если они есть
            $inputElement.siblings(".suggestion-box").remove();

            // Создаем контейнер для подсказок
            const $suggestionBox = $("<div>", {class: "suggestion-box"});

            // Добавляем подсказки
            suggestions.forEach((suggestion) => {
                const $item = $("<div>", {class: "suggestion-item", text: suggestion.value});
                $item.on("click", () => {
                    // Выполняем callback, если передан
                    onSelect(suggestion);

                    // Удаляем подсказки после выбора
                    $suggestionBox.remove();
                });
                $suggestionBox.append($item);
            });

            // Добавляем контейнер к родителю поля ввода
            $inputElement.parent().append($suggestionBox);
        }

        $('input[name="region"]').on('focus', function (e) {
            e.preventDefault();
            console.log(e.target.value);
        });

        $('input[type="text"]').on('focusout', function (e) {
            e.preventDefault();

            $labelElement = $(this).parent().parent().parent().find('label').text();

            var $zip;

            if ($labelElement == 'Страна:') {
                //setValueInput($(this),'Россия');
            }
            if ($labelElement == 'Почтовый индекс:') {
                $zip = $(this);
                setFull($zip);
            }
            if ($labelElement == 'Регион:') {
                $region = $(this);
                setFull($region);
            }
            if ($labelElement == 'Район:') {
                $district = $(this);
                setFull($district);
            }
            if ($labelElement == 'Населенный пункт:') {
                $city = $(this);
                setFull($city);
            }
            if ($labelElement == 'Улица, номер дома:' || $labelElement == 'Улица:') {
                $(this).parent().parent().parent().find('label').text('Улица:');
                $street = $(this);
                setFull($street);
            }
            if ($labelElement == 'Номер дома:') {
                $building = $(this);
                setFull($building);
            }
            if ($labelElement == 'Квартира, офис, комната, этаж:') {
                $other = $(this);
                setFull($other);
            }

        });
    }));

});

function saveToFile(filename, content) {
    const blob = new Blob([content], { type: "text/plain" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
};
function safeStringify(obj) {
    const seen = new WeakSet();
    return JSON.stringify(obj, (key, value) => {
        if (typeof value === "object" && value !== null) {
            if (seen.has(value)) {
                return; // Убираем циклические ссылки
            }
            seen.add(value);
        }
        return value;
    }, 2);
};
function setZip($input, $code) {
    $input.attr('value', $code);
    $input.val($code);
}
function showSelected(suggestion) {
    console.log(suggestion);
    var address = suggestion.data;
}
function setValueInput($input, $text) {
    $input.val($text);
    $input.attr('value', $text);
    $input.attr('title', $text);
    const event = new Event('change', { bubbles: true, cancelable: true });
    $input[0].dispatchEvent(event);
    /*$input.trigger('kladr-change',$input);*/
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

    $zip = $this.parent().parent().parent().parent().find('input[name="zip"]').val();
    $country = $this.parent().parent().parent().parent().find('input[name="country"]').val();
    $region = $this.parent().parent().parent().parent().find('input[name="region"]').val();
    $area = $this.parent().parent().parent().parent().find('input[name="area"]').val();
    $citySettlement = $this.parent().parent().parent().parent().find('input[name="city-settlement"]').val();
    $street = $this.parent().parent().parent().parent().find('input[name="street"]').val();
    $house = $this.parent().parent().parent().parent().find('input[name="house"]').val();
    $other = $this.parent().parent().parent().parent().find('input[name="other"]').val();

    $fullAddress = $zip +', '+$country+', '+$region+', '+$area+', '+$citySettlement+', ' + $street + ', ' + $house + ', ' + $other;
    $addressFullInput = $this.parents('div.crm-address-control-item').find('.crm-address-search-control-block').find('input');
    setValueInput($addressFullInput,$fullAddress);

    //console.log($fullAddress);
}

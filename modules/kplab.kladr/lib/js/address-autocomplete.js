let currentController;
let isMouseOverSuggestionBox = false;

// Храним выбранные FIAS-идентификаторы для каждого уровня
let selectedRegion = null;
let selectedArea = null;
let selectedCity = null;
let selectedSettlement = null;
let selectedStreet = null;
let selectedHouse = null;
let selectedBlock = null;
let selectedStead       = null;
let selectedFlat       = null;

const TOKEN = "5b9fdc5d0fea8d0a58d94d34289b2551a31c2f75";

/**
 * Делает POST к Dadata suggestions
 */
async function fetchSuggestions(query, bounds, constraints = []) {
    if (currentController) {
        currentController.abort();
    }
    currentController = new AbortController();

    try {
        const payload = {
            query,
            from_bound: { value: bounds.from },
            to_bound:   { value: bounds.to },
            restrict_value: true,
            locations: constraints
        };
        const res = await fetch(
            "https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "Authorization": "Token " + TOKEN
                },
                body: JSON.stringify(payload),
                signal: currentController.signal
            }
        );
        const json = await res.json();
        return json.suggestions || [];
    } catch (e) {
        if (e.name !== "AbortError") {
            console.error("fetchSuggestions error:", e);
        }
        return [];
    }
}

/**
 * Обновляет jQuery-поле и триггерит change
 */
function updateInput($input, value) {
    if (!$input || !$input.length || value == null) return;
    $input.val(value).attr("title", value);
    $input[0].dispatchEvent(new Event("change", { bubbles: true }));
}

/**
 * Собираем карту полей по атрибуту name
 * + ловим поле «Корпус/Строение», у которого name нет
 */
function getFieldMap($container) {
    const map = {};
    $container.find("input.ui-ctl-element").each(function() {
        const $i = $(this);
        let name = $i.attr("name");
        if (!name) {
            // ловим «Корпус/Строение»
            const label = $i.closest(".ui-entity-editor-content-block")
                .find("label").text().trim().replace(/[:：]/g,"");
            if (label === "Строение") {
                name = "houseBlockS";
            } else {
                return; // пропускаем прочие без name
            }
            if (label === "Корпус") {
                name = "houseBlockK";
            } else {
                return; // пропускаем прочие без name
            }
        }
        map[name] = $i;
    });
    return map;
}

function setValueInput($input, $text) {
    $input.val($text);
    $input.attr('value', $text);
    $input.attr('title', $text);
    const event = new Event('change', { bubbles: true, cancelable: true });
    $input[0].dispatchEvent(event);
    /*$input.trigger('kladr-change',$input);*/
}

/**
 * Простой debounce
 */
function debounce(fn, ms) {
    let t;
    return function(...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), ms);
    };
}
function getInputLabel(input) {
    const outerBlock = input.closest('.ui-entity-editor-content-block.ui-entity-editor-field-text');
    if (!outerBlock) return undefined;
    const label = outerBlock.querySelector('.ui-entity-editor-block-title label');
    return label ? label.textContent.trim().replace(/[:：]/g, '') : undefined;
}


/**
 * Рисует список подсказок
 */
function showSuggestions($input, suggestions, onSelect) {
    $input = $($input);
    $input.siblings(".suggestion-box").remove();
    if (!suggestions || !suggestions.length) return;

    const $box = $("<div>", { class: "suggestion-box" })
        .on("mouseenter", () => isMouseOverSuggestionBox = true)
        .on("mouseleave", () => isMouseOverSuggestionBox = false);

    suggestions.forEach(s => {
        const $item = $("<div>", { class: "suggestion-item", text: s.value });
        $item
            .on("mousedown", () => isMouseOverSuggestionBox = true)
            .on("click", () => {
                onSelect(s);
                $box.remove();
                isMouseOverSuggestionBox = false;
            });
        $box.append($item);
    });

    $input.parent().append($box);
}


/**
 * Подключает автокомплит ко всем address-блокам
 */
function bindAutocompleteInputs() {
    document.querySelectorAll(".crm-address-control-item").forEach(item => {
        const $c      = $(item);
        const fields  = getFieldMap($c);

        // Привязываем подсказки только к нужным полям
        ["region","area","city","settlement","street","house","stead","flat"].forEach(name => {
            const $inp = fields[name];
            if (!$inp || $inp.data("autocomplete-bound")) return;
            $inp.data("autocomplete-bound", true);

            $inp.on("input", debounce(async function() {
                let q = $inp.val().trim();
                if (q.length < 2) return;

                // изначально bounds повторяет name
                let bounds = { from: name, to: name };
                let constraints = [];

                // bounds — только если нужно ограничить глубину
                switch(name) {
                    case 'region':
                        break;
                    case 'area':
                        if(selectedRegion) constraints.push({ region_fias_id: selectedRegion.fias_id });
                        break;
                    case 'city':
                        if (selectedRegion) constraints.push({ region_fias_id: selectedRegion.fias_id });
                        if (selectedArea) constraints.push({ area_fias_id: selectedArea.fias_id });
                        break;
                    case 'settlement':
                        if (selectedRegion) constraints.push({ region_fias_id: selectedRegion.fias_id });
                        if (selectedArea) constraints.push({ area_fias_id: selectedArea.fias_id });
                        break;
                    case 'street':
                        if (selectedCity) constraints.push({ city_fias_id: selectedCity.fias_id });
                        if (selectedSettlement) constraints.push({ settlement_fias_id: selectedSettlement.fias_id });
                        break;
                    case 'house':
                        if (selectedSettlement) constraints.push({ settlement_fias_id: selectedSettlement.fias_id });
                        if (selectedStreet) constraints.push({ street_fias_id: selectedStreet.fias_id });
                        break;
                    case 'stead':
                        if (selectedStreet) constraints.push({ street_fias_id: selectedStreet.fias_id });
                        break;
                    case 'flat':
                        // Dadata не поддерживает прямой flat, но позволим вверх до дома
                        bounds = { from: 'house-flat', to: 'house-flat' };
                        if (selectedStreet) constraints.push({ street_fias_id: selectedStreet.fias_id });
                        q = `${selectedHouse.house_type_full} ${selectedHouse.house}${selectedHouse.block_type_full ? ", "+selectedHouse.block_type_full+" "+selectedHouse.block : ""}, кв. ${q}`;
                        break;
                }

                const sugg = await fetchSuggestions(q, bounds, constraints);
                showSuggestions($inp, sugg, selected => {
                    const d = selected.data;
                    if (!d) return; // защищаемся

                    switch (name) {
                        case "region":
                            selectedRegion = d;
                            updateInput($inp, d.region);
                            updateInput(fields["country"], d.country);
                            updateInput(fields["fiasId"],  d.fias_id);
                            break;

                        case "area":
                            selectedArea = d;
                            updateInput($inp, d.area);
                            updateInput(fields["fiasId"],  d.fias_id);
                            break;

                        case "city":
                            selectedCity = d;
                            updateInput($inp, d.city);
                            updateInput(fields["fiasId"],  d.fias_id);
                            break;

                        case "settlement":
                            selectedSettlement = d;
                            updateInput($inp, d.settlement);
                            updateInput(fields["fiasId"],    d.fias_id);
                            break;

                        case "street":
                            selectedStreet = d;
                            updateInput($inp, d.street);
                            updateInput(fields["fiasId"],     d.fias_id);
                            break;

                        case "house":
                            selectedHouse = d;
                            const txt = ((d.house||"")).trim();
                            updateInput($inp, txt);
                            updateInput(fields["zip"],    d.postal_code);
                            updateInput(fields["fiasId"], d.fias_id);
                            if (d.block_type_full === "корпус") {
                                updateInput(fields["houseBlockK"], d.block);
                            }
                            if (d.block_type_full === "строение") {
                                updateInput(fields["houseBlockS"], d.block);
                            }
                            break;

                        case "stead":
                            selectedStead = d;
                            const txtStead = ((d.stead||"")).trim();
                            updateInput($inp, txtStead);
                            updateInput(fields["zip"],    d.postal_code);
                            updateInput(fields["fiasId"], d.fias_id);
                            break;

                        case "block":
                            selectedBlock = d;
                            if (d.block_type_full === "корпус") {
                                updateInput(fields["houseBlockK"], d.block);
                            }
                            if (d.block_type_full === "строение") {
                                updateInput(fields["houseBlockS"], d.block);
                            }
                            updateInput(fields["zip"],    d.postal_code);
                            updateInput(fields["fiasId"], d.fias_id);
                            break;

                        case "flat":
                            // номер квартиры
                            selectedFlat = d;
                            updateInput($inp, d.flat);
                            updateInput(fields["fiasId"], d.fias_id);
                            break;
                    }
                });
            }, 300));
        });
    });
}

// Инициализация
BX.ready(() => {
    bindAutocompleteInputs();
    BX.addCustomEvent(window, "oncontrolchanged", bindAutocompleteInputs);

    // клик вне — закрываем подсказки
    $(document).on("click", () => {
        if (!isMouseOverSuggestionBox) {
            $(".suggestion-box").remove();
        }
    });
});

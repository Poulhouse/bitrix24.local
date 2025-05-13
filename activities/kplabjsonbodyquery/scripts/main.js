let updateObjectKeyTimeout;
function convertToFields() {
    let jsonText = $("#request_body").val().trim();
    let container = $("#json-builder");

    if (jsonText === "") {
        alert("⚠️ Поле JSON пустое. Вставьте JSON перед преобразованием!");
        return;
    }

    try {
        let parsedJson = JSON.parse(jsonText);
        container.empty(); // Очищаем контейнер
        restoreJsonRecursive(parsedJson, container.attr("id")); // Используем рекурсивное восстановление
        //$("#request_body").prop("readonly", true);
        //checkJsonBuilderEmpty();
    } catch (e) {
        alert("❌ Ошибка: Некорректный JSON!");
        console.error(e);
    }
}
function restoreJsonFields() {
    let storedJson = $("#request_body").val();
    let container = $("#json-builder");

    //console.log("🔄 Восстанавливаем JSON:", storedJson);

    container.empty(); // Очищаем контейнер перед восстановлением

    if ($.trim(storedJson) !== "") {
        try {
            let parsedJson = JSON.parse(storedJson);
            restoreJsonRecursive(parsedJson, container.attr("id")); // Передаем ID контейнера
        } catch (e) {
            console.error("❌ Ошибка парсинга JSON:", e);
        }
    }
}
function restoreJsonRecursive(parsedJson, parentId) {
    $.each(parsedJson, function (key, value) {
        let type = typeof value;

        console.log(`▶ Восстанавливаем: ${key} →`, value, `(тип: ${type}) в #${parentId}`);

        if (Array.isArray(value)) {
            let arrayContainerId = addArray(key, [], parentId);

            $.each(value, function (index, arrayItem) {
                if (typeof arrayItem === "object" && arrayItem !== null) {
                    addObjectToArray(arrayContainerId); // 🔥 Добавляем объект без ключа
                    restoreJsonRecursive(arrayItem, `array-${arrayContainerId}`);
                } else {
                    addElementToArray(arrayContainerId, arrayItem, typeof arrayItem);
                }
            });
        } else if (type === "object" && value !== null) {
            let objectContainerId = addObject(key, value, parentId);
            restoreJsonRecursive(value, objectContainerId);
        } else {
            addField(parentId, key, value, type);
        }
    });
}
function updateRequestBodyLabel() {
    let objectBtn = $("#objectBtn");
    let arrayBtn = $("#arrayBtn");

    //generateBtn.attr("onclick", `generate()`);
    generateJSON();

    objectBtn.show();
    arrayBtn.show();
}
function toggleAuthFields() {
    let authMethod = $("#add_to").val();
    let keyRow = $("#auth_key_row");
    let valueRow = $("#auth_value_row");

    if (authMethod === "Header" || authMethod === "Query") {
        keyRow.show();
        valueRow.show();
    } else {
        keyRow.hide();
        valueRow.hide();
    }
}
function formatValue(value, type) {
    switch (type) {
        case "number": return Number(value);
        case "boolean": return value.toLowerCase() === "true";
        case "null": return null;
        default: return value;
    }
}
function parseJsonAndBuildUI(json, parentId = "json-builder") {
    Object.entries(json).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            let arrayElement = createHtmlElement('array', key, parentId);
            $(`#${parentId}`).append(arrayElement);
            value.forEach((item) => {
                if (typeof item === "object") {
                    let objElem = createHtmlElement("object_not_input", null, `array-${parentId}-${key}`);
                    $(`#array-${parentId}-${key}`).append(objElem);
                    parseJsonAndBuildUI(item, `object-${parentId}-${key}`);
                } else {
                    let fieldElem = createHtmlElement("field", null, `array-${parentId}-${key}`, item);
                    $(`#array-${parentId}-${key}`).append(fieldElem);
                }
            });
        } else if (typeof value === "object" && value !== null) {
            let objElem = createHtmlElement("object", key, parentId);
            $(`#${parentId}`).append(objElem);
            parseJsonAndBuildUI(value, `object-${parentId}-${key}`);
        } else {
            let fieldElem = createHtmlElement("field", key, parentId, value);
            $(`#${parentId}`).append(fieldElem);
        }
    });
}
function parseFields(container) {
    let json = {};
    console.log(`🔍 Парсим контейнер:`, container);

    container.children().each(function () {
        let field = $(this);

        // 🔹 Обычные поля (ключ-значение)
        if (field.hasClass("json-field")) {
            processJsonField(field, json);
        }

        // 🔹 Объекты { key: { ... } }
        else if (field.hasClass("object-field")) {
            processJsonObject(field, json);
        }

        // 🔹 Массивы [ {...}, {...} ]
        else if (field.hasClass("array-field")) {
            processJsonArray(field, json);
        }
    });

    console.log(`📌 Итоговый JSON:`, JSON.stringify(json, null, 4));
    return json;
}
function processJsonField(field, json) {
    let key = field.find("input[name='json_keys[]']").val().trim();
    let value = field.find("input[name='json_values[]']").val().trim();

    if (!key) {
        console.warn(`⚠️ Пропущено поле без ключа.`);
        return;
    }

    json[key] = value;
    console.log(`🔑 Поле: ${key} → ${value}`);
}
function processJsonObject(field, json) {
    let key = field.attr("data-original-key") || field.find("input[name='json_keys[]']").val().trim();

    if (!key) {
        console.warn(`⚠️ Пропущен объект без ключа.`);
        return;
    }

    // Проверяем, нет ли уже этого объекта в JSON
    if (!json.hasOwnProperty(key)) {
        json[key] = {};
    }

    // Парсим вложенные поля
    let parsedData = parseFields(field.find(".json-object"));

    // Добавляем объект, только если он не пустой
    if (Object.keys(parsedData).length > 0) {
        json[key] = parsedData;
        console.log(`🗂️ Обработан объект: ${key}`, json[key]);
    } else {
        console.warn(`⚠️ Пустой объект ${key}, пропускаем.`);
    }
}
function processJsonArray(field, json) {
    let key = field.find("input[name='json_keys[]']").val().trim();
    if (!key) {
        console.warn(`⚠️ Пропущен массив без ключа.`);
        return;
    }

    let arrayData = [];
    field.find(".json-array > .object-field").each(function () {
        let objData = parseFields($(this).find(".json-object"));
        if (Object.keys(objData).length > 0) {
            arrayData.push(objData);
            console.log(`📌 Добавлен объект в массив ${key}:`, objData);
        } else {
            console.warn(`⚠️ Пустой объект в массиве ${key}, пропускаем.`);
        }
    });

    json[key] = arrayData;
    console.log(`📌 Итоговый массив для ${key}:`, json[key]);
}
function createButton(label, onClickAction) {
    return $('<button>', {
        type: 'button',
        text: label,
        click: onClickAction
    });
}
function createField(key = '', value = '', type = 'string') {
    let field = $('<div>', { class: 'json-field' });

    let keyInput = $('<input>', {
        type: 'text',
        name: 'json_keys[]',
        placeholder: 'Ключ',
        value: key
    });

    let typeSelect = $('<select>', { name: 'json_types[]' });
    ['string', 'number', 'boolean', 'null'].forEach(option => {
        typeSelect.append($('<option>', {
            value: option,
            text: option,
            selected: option === type
        }));
    });

    let valueInput = $('<input>', {
        type: 'text',
        name: 'json_values[]',
        placeholder: 'Значение',
        value: value
    });

    let removeBtn = createButton('✖', function () {
        field.remove();
    });

    field.append(removeBtn, keyInput, typeSelect, valueInput);
    return field;
}
function createObject(key = 'newObject') {
    let objectField = $('<div>', { class: 'object-field', 'data-key': key });

    let keyInput = $('<input>', {
        type: 'text',
        name: 'json_keys[]',
        placeholder: 'Ключ',
        value: key,
        oninput: function () { updateObjectKey(this); }
    });

    let objectContainer = $('<div>', { class: 'json-object' });

    let actions = $('<div>', { class: 'json-actions' }).append(
        createButton('Добавить поле', function () { objectContainer.append(createField()); }),
        createButton('Добавить массив', function () { objectContainer.append(createArray()); }),
        createButton('Добавить объект', function () { objectContainer.append(createObject()); })
    );

    let removeBtn = createButton('✖', function () {
        objectField.remove();
    });

    objectField.append(removeBtn, keyInput, $('<span>').text(' { '), objectContainer, actions, $('<span>').text(' } '));
    return objectField;
}
function createArray(key = 'newArray') {
    let arrayField = $('<div>', { class: 'array-field', 'data-key': key });

    let keyInput = $('<input>', {
        type: 'text',
        name: 'json_keys[]',
        placeholder: 'Ключ',
        value: key,
        oninput: function () { updateArrayKey(this); }
    });

    let arrayContainer = $('<div>', { class: 'json-array' });

    let actions = $('<div>', { class: 'json-actions' }).append(
        createButton('Добавить элемент', function () { arrayContainer.append(createField()); }),
        createButton('Добавить объект', function () { arrayContainer.append(createObject()); })
    );

    let removeBtn = createButton('✖', function () {
        arrayField.remove();
    });

    arrayField.append(removeBtn, keyInput, $('<span>').text(' [ '), arrayContainer, actions, $('<span>').text(' ] '));
    return arrayField;
}
function createHtmlElement(type, key, parentId, value = "") {
    let uniqueKey = key || generateUniqueKey(type);
    let elementId = `${parentId}-${uniqueKey}`;

    const actionButtons = (id) => `
        <div class="json-actions">
            <button type="button" onclick="addFieldToObject('${id}')">Добавить поле</button>
            <button type="button" onclick="addArrayToObject('${id}')">Добавить массив</button>
            <button type="button" onclick="addObjectToObject('${id}')">Добавить объект</button>
        </div>`;

    let templates = {
        field: `
            <div class="json-field" id="${elementId}">
                <button type="button" class="remove-btn" onclick="removeElement('${elementId}')">✖</button>
                <input type="text" name="json_keys[]" placeholder="Ключ" value="${uniqueKey}">
                <select name="json_types[]">
                    <option value="string" selected>string</option>
                    <option value="number">number</option>
                    <option value="boolean">boolean</option>
                    <option value="null">null</option>
                </select>
                <input type="text" id="${elementId}_value" name="json_values[]" placeholder="Значение" value="${value}">
                <input type="button" value="..." onclick="BPAShowSelector('${elementId}_value', 'string', '');">
            </div>
        `,
        object: `
            <div class="object-field" id="${elementId}">
                <button type="button" class="remove-btn" onclick="removeElement('${elementId}')">✖</button>
                <input type="text" name="json_keys[]" placeholder="Ключ" value="${uniqueKey}">
                <span> { </span>
                <div id="object-${elementId}" class="json-object"></div>
                <span> } </span>
                ${actionButtons(elementId)}
            </div>
        `,
        array: `
            <div class="array-field" id="${elementId}">
                <button type="button" class="remove-btn" onclick="removeElement('${elementId}')">✖</button>
                <input type="text" name="json_keys[]" placeholder="Ключ" value="${uniqueKey}">
                <span> [ </span>
                <div id="array-${elementId}" class="json-array"></div>
                <span> ] </span>
                <div class="json-actions">
                    <button type="button" onclick="addElementToArray('${elementId}')">Добавить элемент</button>
                    <button type="button" onclick="addObjectToArray('${elementId}')">Добавить объект</button>
                    <button type="button" onclick="addArrayToArray('${elementId}')">Добавить массив</button>
                </div>
            </div>
        `,
        object_not_input: `
            <div class="object-field" id="${elementId}">
                <button type="button" class="remove-btn" onclick="removeElement('${elementId}')">✖</button>
                <span> { </span>
                <div id="object-${elementId}" class="json-object"></div>
                <span> } </span>
                ${actionButtons(elementId)}
            </div>
        `
    };

    return $(templates[type]); // Возвращает jQuery-объект
}
function addField(parentId = "json-builder") {
    let fieldElement = createHtmlElement('field', null, parentId);
    $(`#${parentId}`).append(fieldElement);
}
function addArray(parentId = "json-builder") {
    let arrayElement = createHtmlElement('array', null, parentId);
    $(`#${parentId}`).append(arrayElement);
}
function addObject(parentId = "json-builder") {
    let objectElement = createHtmlElement('object', null, parentId);
    $(`#${parentId}`).append(objectElement);
}
function addFieldToObject(parentId) {
    let fieldElement = createHtmlElement('field', null, `object-${parentId}`);
    $(`#object-${parentId}`).append(fieldElement);
}
function addArrayToObject(parentId) {
    let arrayElement = createHtmlElement('array', null, `object-${parentId}`);
    $(`#object-${parentId}`).append(arrayElement);
}
function addObjectToObject(parentId) {
    let objectElement = createHtmlElement('object', null, `object-${parentId}`);
    $(`#object-${parentId}`).append(objectElement);
}
function addElementToArray(parentId) {
    let fieldElement = createHtmlElement('field', null, `array-${parentId}`);
    $(`#array-${parentId}`).append(fieldElement);
}
function addObjectToArray(parentId) {
    let objectElement = createHtmlElement('object_not_input', null, `array-${parentId}`);
    $(`#array-${parentId}`).append(objectElement);
}
function addArrayToArray(parentId) {
    let arrayElement = createHtmlElement('array', null, `array-${parentId}`);
    $(`#array-${parentId}`).append(arrayElement);
}
function removeElement(elementId) {
    $(`#${elementId}`).remove();
    console.log(`❌ Удалён элемент: ${elementId}`);
}
function generateUniqueKey(prefix = "field") {
    return `${prefix}-${Date.now()}`;
}
function generateJSON(parentId = "json-builder") {
    let json = {};

    $(`#${parentId}`).children(".json-field, .object-field, .array-field").each(function () {
        let key = $(this).find('input[name="json_keys[]"]').val();
        let type = $(this).find('select[name="json_types[]"]').val();
        let valueInput = $(this).find('input[name="json_values[]"]');

        if ($(this).hasClass("json-field")) {
            let value = parseValue(valueInput.val(), type);
            json[key] = value;
        } else if ($(this).hasClass("object-field")) {
            json[key] = generateJSON(`object-${parentId}-${key}`);
        } else if ($(this).hasClass("array-field")) {
            json[key] = [];
            $(this).find(".json-array > div").each(function () {
                if ($(this).hasClass("object-field")) {
                    json[key].push(generateJSON($(this).attr("id"))); // 🔥 Добавляем объект без ключа в массив
                } else if ($(this).hasClass("json-field")) {
                    let arrayValue = parseValue($(this).find('input[name="json_values[]"]').val(), "string");
                    json[key].push(arrayValue);
                }
            });
        }
    });
    $("#request_body").val(JSON.stringify(json, null, 2));
    return json;
}
function parseValue(value, type) {
    switch (type) {
        case "number": return Number(value);
        case "boolean": return value === "true";
        case "null": return null;
        default: return value;
    }
}
function sendTestRequest() {
    let url = $("#url").val().trim();
    let method = $("#method").val();
    let contentType = $("#content_type").val();
    let addTo = $("#add_to").val().trim();
    let authKey = $("#auth_key").val().trim();
    let authValue = $("#auth_value").val().trim();
    let requestBody = $("#request_body").val();

    let headers = {
        "Content-Type": contentType
    };

    // Добавляем авторизацию в заголовки
    if (addTo === "Header" && authKey && authValue) {
        headers[authKey] = authValue;
    }

    // Добавляем авторизацию в URL (Query)
    if (addTo === "Query" && authKey && authValue) {
        let separator = url.includes("?") ? "&" : "?";
        url += `${separator}${encodeURIComponent(authKey)}=${encodeURIComponent(authValue)}`;
    }

    // 🔥 проверяем JSON и форматируем
    try {
        let parsedJson = JSON.parse(requestBody);
        requestBody = JSON.stringify(parsedJson);
    } catch (e) {
        alert("Ошибка: Некорректный JSON! Проверьте данные.");
        return;
    }
    console.log("Отправляемый JSON:", requestBody);

    $.ajax({
        url: url,
        type: method,
        headers: headers,
        data: requestBody,
        contentType: contentType,
        processData: false, // Не превращаем в query-параметры
        success: function(response) {
            $("#response").text("Успешный ответ:\n" + JSON.stringify(response, null, 4));
        },
        error: function(xhr, textStatus, errorThrown) {
            let errorMessage = "Ошибка запроса";

            try {
                let errResponse = JSON.parse(xhr.responseText);
                if (errResponse.errors && Array.isArray(errResponse.errors)) {
                    errorMessage = errResponse.errors.map(err => err.message).join("\n");
                } else {
                    errorMessage = errResponse.message || "Неизвестная ошибка";
                }
            } catch (e) {
                errorMessage = "Ошибка парсинга JSON-ответа от сервера";
            }

            $("#response").text(`Ошибка: ${textStatus} - ${errorThrown}\n${errorMessage}`);
        }
    });
}
function updateArrayKey(input, oldId, parentId) {
    let newKey = $(input).val().trim();
    if (!newKey) return;

    let arrayField = $(input).closest(".array-field");
    if (!arrayField.length) return;

    let arrayContainer = $("#" + oldId);
    if (!arrayContainer.length) return;

    let newId = `array-${newKey}-${Date.now()}`; // Уникальный ID при изменении ключа

    console.log(`🔄 Обновляем ключ массива: ${oldId} → ${newId}`);

    arrayField.attr("data-key", newKey);
    arrayContainer.attr("id", newId);

    // Обновляем все кнопки внутри массива
    arrayField.find("button[onclick^='addElementToArray']").attr("onclick", `addElementToArray('${newId}')`);
    arrayField.find("button[onclick^='addObjectToArray']").attr("onclick", `addObjectToArray('${newId}')`);

    // Обновляем обработчик oninput, чтобы он работал с новым ID
    $(input).attr("oninput", `updateArrayKey(this, '${newId}', '${parentId}')`);
    generateJSON();
}
function updateObjectToObjectKey(input, uniqueId, parentId) {
    let objectField = $(input).closest(".object-field");
    let newKey = $(input).val().trim();

    if (!newKey) {
        console.warn(`⚠️ Пустой ключ, изменение отменено.`);
        return;
    }

    console.log(`🔄 Обновляем объект:`, objectField); // Лог структуры объекта

    // Обновляем `data-key` и `id`
    objectField.attr("data-key", newKey);
    let newObjectId = `object-${newKey}-${uniqueId}`;
    objectField.find(".json-object").attr("id", newObjectId);

    console.log(`📝 Новый ключ: ${newKey}`);
    console.log(`🔄 Новый ID: ${newObjectId}`);

    // Обновляем кнопки "Добавить поле", "Добавить объект", "Добавить массив"
    objectField.find(".json-actions button").each(function () {
        let oldOnClick = $(this).attr("onclick");
        console.log(`⚙️ Старая функция:`, oldOnClick);
        if (oldOnClick) {
            let updatedOnClick = oldOnClick.replace(/'object-[^']+'/g, `'${newObjectId}'`);
            $(this).attr("onclick", updatedOnClick);
            console.log(`🔄 Новая функция:`, updatedOnClick);
        }
    });

    console.log(`✅ Ключ обновлён: "${newKey}", новый ID: "${newObjectId}"`);
}
function updateObjectKey(input) {
    clearTimeout(updateObjectKeyTimeout);

    updateObjectKeyTimeout = setTimeout(() => {
        let objectField = $(input).closest(".object-field");
        let newKey = input.value.trim();
        if (!newKey) return;

        let objectContainer = objectField.find(".json-object");
        let oldId = objectContainer.attr("id");
        let newId = `object-${newKey.replace(/\s+/g, "-")}-${Date.now()}`;

        objectField.attr("data-key", newKey);
        objectContainer.attr("id", newId);

        objectField.find(".json-actions button").each(function () {
            let onclickAttr = $(this).attr("onclick");
            if (onclickAttr) {
                let updatedOnClick = onclickAttr.replace(oldId, newId);
                $(this).attr("onclick", updatedOnClick);
            }
        });

        console.log(`🔄 Ключ обновлен: "${newKey}", ID изменен на "${newId}"`);
    }, 500);
}




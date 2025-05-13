// ========================
// 📌 JSON Editor - main.js
// ========================

// 🔥 Глобальная переменная для хранения таймера обновления ключа объекта
let updateObjectKeyTimeout;

// ========================
// 📌 Функции для работы с JSON
// ========================

// 🔥 Конвертирует JSON в поля редактирования
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
        restoreJsonRecursive(parsedJson, container.attr("id"));
    } catch (e) {
        alert("❌ Ошибка: Некорректный JSON!");
        console.error(e);
    }
}

// 🔥 Восстанавливает JSON из textarea при загрузке страницы
function restoreJsonFields() {
    let storedJson = $("#request_body").val();
    let container = $("#json-builder");

    container.empty(); // Очищаем контейнер перед восстановлением

    if ($.trim(storedJson) !== "") {
        try {
            let parsedJson = JSON.parse(storedJson);
            restoreJsonRecursive(parsedJson, container.attr("id"));
        } catch (e) {
            console.error("❌ Ошибка парсинга JSON:", e);
        }
    }
}

// 🔥 Рекурсивное восстановление JSON в виде полей
function restoreJsonRecursive(parsedJson, parentId) {
    $.each(parsedJson, function (key, value) {
        let type = typeof value;

        console.log(`▶ Восстанавливаем: ${key} →`, value, `(тип: ${type}) в #${parentId}`);

        if (Array.isArray(value)) {
            console.log(`🔄 Восстанавливаем массив: ${key} (количество элементов: ${value.length}) в #${parentId}`);

            let arrayContainerId = addArray(parentId, key);

            value.forEach((arrayItem, index) => {
                if (typeof arrayItem === "object" && arrayItem !== null) {
                    let objId = addObjectToArray(arrayContainerId);
                    console.log(`🔄 Вставляем объект ${index} в массив ${key}: ID → ${objId}`);
                    restoreJsonRecursive(arrayItem, objId);
                } else {
                    // Определяем тип элемента массива
                    let elementType = typeof arrayItem;
                    if (arrayItem === null) elementType = "null";
                    if (typeof arrayItem === "string" && arrayItem.match(/\{\{.+?\}\}/)) {
                        // Это шаблон - сохраняем как строку
                        elementType = "string";
                    }
                    console.log(`📌 Вставляем примитив ${index} в массив ${key}: ${arrayItem}`);
                    addElementToArray(arrayContainerId, arrayItem, elementType);
                }
            });
        } else if (type === "object" && value !== null) {
            let objectContainerId = addObject(parentId, key);
            restoreJsonRecursive(value, objectContainerId);
        } else {
            // Определяем тип значения
            let valueType = type;
            if (value === null) valueType = "null";
            if (typeof value === "string" && value.match(/\{\{.+?\}\}/)) {
                // Это шаблон - сохраняем как строку
                valueType = "string";
            }
            addField(parentId, key, value, valueType);
        }
    });
}

// 🔥 Генерирует JSON из полей
function generateJSON(parentId = "json-builder") {
    let json = {};

    $(`#${parentId}`).children(".json-field, .object-field, .array-field").each(function () {
        let key = $(this).find('input[name="json_keys[]"]').val();
        let type = $(this).find('select[name="json_types[]"]').val();
        let valueInput = $(this).find('input[name="json_values[]"]');

        // 📌 Если .json-field
        if ($(this).hasClass("json-field")) {
            json[key] = parseValue(valueInput.val(), type);
        }
        // 📌 Если .object-field
        else if ($(this).hasClass("object-field")) {
            let objId = $(this).find('.json-object').attr("id");
            json[key] = generateJSON(objId);
        }
        // 📌 Если .array-field
        else if ($(this).hasClass("array-field")) {
            let arrayData = [];
            let arrayId = $(this).find('.json-array').attr("id");

            // Проходим по элементам массива
            $(this)
                .find(".json-array > .object_not_input-field, .json-array > .element-field")
                .each(function () {
                if ($(this).hasClass("element-field")) {
                    // Если это простое поле (не объект)
                    let elementValue = $(this).find("input[name='json_values[]']").val();
                    let elementType = $(this).find("select[name='json_types[]']").val();
                    let arrayValue = parseValue(elementValue, elementType);

                    // ✅ Проверяем, есть ли родительский массив
                    let parentArrayId = $(this).closest(".json-array").attr("id");

                    if (parentArrayId === arrayId) {
                        arrayData.push(arrayValue); // ✅ Добавляем объект только в свой массив!
                        console.log(`📌 Добавлен элемент в массив ${key}:`, arrayValue);
                    }
                }
                else if ($(this).hasClass("object_not_input-field")) {
                    let objId = $(this).find('.json-object_not_input').attr("id");
                    let objData = generateJSON(objId);

                    // ✅ Проверяем, есть ли родительский массив
                    let parentArrayId = $(this).closest(".json-array").attr("id");

                    if (parentArrayId === arrayId) {
                        arrayData.push(objData); // ✅ Добавляем объект только в свой массив!
                        console.log(`📌 Добавлен объект в массив ${key}:`, objData);
                    }
                }
            });

            json[key] = arrayData;
            console.log(`📌 Итоговый массив для ${key}:`, json[key]);
        }
    });

    $("#request_body").val(JSON.stringify(json, null, 4));
    return json;
}

// 🔥 Функция для парсинга
function parseValue(value, type) {
    switch (type) {
        case "number": return Number(value);
        case "boolean": return value === "true";
        case "null": return null;
        default: return value;
    }
}

// 🔥 Функция для парсинга JSON из UI
function parseFields(container) {
    let json = {};
    console.log(`🔍 Парсим контейнер:`, container);

    container.children().each(function () {
        let field = $(this);

        if (field.hasClass("json-field")) {
            processJsonField(field, json);
        } else if (field.hasClass("object-field")) {
            processJsonObject(field, json);
        } else if (field.hasClass("array-field")) {
            processJsonArray(field, json);
        }
    });

    console.log(`📌 Итоговый JSON:`, JSON.stringify(json, null, 4));
    return json;
}

// 🔥 Обрабатывает поля (ключ-значение)
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

// 🔥 Обрабатывает объекты JSON
function processJsonObject(field, json) {
    let key = field.find("input[name='json_keys[]']").val().trim();

    if (!key) {
        console.warn(`⚠️ Обнаружен объект без ключа, добавляем в массив`);
        key = generateUniqueKey("object"); // Генерируем уникальный ключ
    }

    json[key] = parseFields(field.find(".json-object"));
    console.log(`🗂️ Обработан объект: ${key}`, json[key]);
}

// 🔥 Обрабатывает массивы JSON
function processJsonArray(field, json) {
    let key = field.find("input[name='json_keys[]']").val().trim();
    if (!key) {
        console.warn(`⚠️ Пропущен массив без ключа.`);
        return;
    }

    let arrayData = [];

    // Обрабатываем все объекты внутри массива
    field.find(".json-array > .object_not_input-field, .json-array > .element-field").each(function () {
        if ($(this).hasClass("element-field")) {
            // Если это простое поле (не объект)
            let value = $(this).find("input[name='json_values[]']").val().trim();
            arrayData.push(value);
        }
        else {
            // Если это объект, **парсим его**
            let objData = parseFields($(this).find(".json-object"));
            console.log(`📌 Добавлен объект в массив ${key}:`, objData);

            // 🔥 Исправленный код: **добавлять объект даже если он пустой**
            arrayData.push(objData);
        }
    });

    json[key] = arrayData;
    console.log(`📌 Итоговый массив для ${key}:`, json[key]);
}



// ========================
// 📌 Обработчики кликов (onclick) и ввода (oninput)
// ========================

// 🔥 Добавляет поле (ключ-значение)
function addField(parentId = "json-builder", key = "", value = "", type = "string") {
    let fieldElement = createHtmlElement('field', key, parentId, value, type);
    $(`#${parentId}`).append(fieldElement);
    generateJSON();
    return fieldElement.attr("id"); // Возвращаем ID
}

// 🔥 Добавляет массив
function addArray(parentId = "json-builder", key = "newArray") {
    let arrayElement = createHtmlElement('array', key, parentId);
    $(`#${parentId}`).append(arrayElement);
    generateJSON();
    return arrayElement.find('.json-array').attr("id"); // Возвращаем ID
}

// 🔥 Добавляет объект
function addObject(parentId = "json-builder", key = "newObject") {
    let objectElement = createHtmlElement('object', key, parentId);
    $(`#${parentId}`).append(objectElement);
    generateJSON();
    return objectElement.find('.json-object').attr("id"); // Возвращаем ID
}

// 🔥 Добавляет поле в объект
function addFieldToObject(parentId) {
    console.error(`🔥 Добавляем поле в объект #${parentId}!`);
    let fieldElement = createHtmlElement( "field", null , `${parentId}`);
    $(`#${parentId}`).append(fieldElement);
    generateJSON();
    return fieldElement.attr("id"); // Возвращаем ID
}

// 🔥 Добавляет массив в объект
function addArrayToObject(parentId) {
    let arrayElement = createHtmlElement('array', null, `${parentId}`);
    $(`#${parentId}`).append(arrayElement);
    generateJSON();
    return arrayElement.find('.json-array').attr("id"); // Возвращаем ID
}

// 🔥 Добавляет объект в объект
function addObjectToObject(parentId) {
    let objectElement = createHtmlElement('object', null, `${parentId}`);
    $(`#${parentId}`).append(objectElement);
    generateJSON();
    return objectElement.find('.json-object').attr("id"); // Возвращаем ID
}

// 🔥 Добавляет элемент в массив
function addElementToArray(parentId, value = "", type = "string") {
    let fieldElement = createHtmlElement('element', null, `${parentId}`, value, type);
    $(`#${parentId}`).append(fieldElement);
    generateJSON();
    return fieldElement.attr("id"); // Возвращаем ID
}

// 🔥 Добавляет объект в массив
function addObjectToArray(parentId) {
    let objectElement = createHtmlElement('object_not_input', null, `${parentId}`);
    $(`#${parentId}`).append(objectElement);
    generateJSON();
    return objectElement.find('.json-object_not_input').attr("id"); // Возвращаем ID
}

// 🔥 Удаляет элемент
function removeElement(elementId) {
    $(`#${elementId}`).remove();
    generateJSON();
}
function removeObjectElement(elementId) {
    $(`#${elementId}`).closest(".json-object").parent().remove();
    generateJSON();
}
function removeObjectNotInputElement(elementId) {
    $(`#${elementId}`).closest(".json-object_not_input").parent().remove();
    generateJSON();
}
function removeArrayElement(elementId) {
    $(`#${elementId}`).closest(".json-array").parent().remove();
    generateJSON();
}

// 🔥 Генерация уникального ключа для элемента
function generateUniqueKey(prefix = "field") {
    return `${prefix}-${Date.now()}`;
}

// 🔥 Получить родителя любого элемента
function getNextIndex(parentId, type) {
    let existingIndexes = [];

    // Ищем все элементы, соответствующие формату ID
    $(`[id^="${parentId}_${type}-"]`).each(function () {
        let match = this.id.match(new RegExp(`${parentId}_${type}-(\\d+)$`)); // Ищем номер в конце ID
        if (match) {
            existingIndexes.push(parseInt(match[1])); // Добавляем найденный индекс
        }
    });

    let nextIndex = existingIndexes.length > 0 ? Math.max(...existingIndexes) + 1 : 0;

    console.log(`📊 Текущий индекс для ${parentId}_${type} → ${nextIndex}`);
    return nextIndex;
}

// ========================
// 📌 Генерация HTML-элементов
// ========================
function createHtmlElement(type, key = null, parentId, value = "", inputType = "string") {
    let index = getNextIndex(parentId, type); // Получаем индекс нового элемента
    let elementKey = key || `${type}_${index}`;
    let elementId = `${parentId}_${type}-${index}`; // Создаем уникальный ID

    console.log(`🔄 Создаем html элемент #${elementId} | type → ${type} | parentId → #${parentId} | value → ${value} | inputType → ${inputType}`);

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
                <input type="text" id="${elementId}_key" name="json_keys[]" placeholder="Ключ" value="${elementKey}" oninput="generateJSON()">
                <select name="json_types[]" oninput="generateJSON()">
                    <option value="string" ${inputType === "string" ? "selected" : ""}>string</option>
                    <option value="number" ${inputType === "number" ? "selected" : ""}>number</option>
                    <option value="boolean" ${inputType === "boolean" ? "selected" : ""}>boolean</option>
                    <option value="null" ${inputType === "null" ? "selected" : ""}>null</option>
                </select>
                <input type="text" id="${elementId}_value" name="json_values[]" placeholder="Значение" value="${value}" oninput="generateJSON()">
                <input type="button" value="..." onclick="BPAShowSelector('${elementId}_value', 'string', '');">
            </div>
        `,
        object: `
            <div class="object-field">
                <button type="button" class="remove-btn" onclick="removeObjectElement('${elementId}')">✖</button>
                <input type="text" id="${elementId}_key" name="json_keys[]" placeholder="Ключ" value="${elementKey}" oninput="generateJSON()">
                <span> { </span>
                <div id="${elementId}" class="json-object"></div>
                ${actionButtons(elementId)}
                <span> } </span>
            </div>
        `,
        array: `
            <div class="array-field">
                <button type="button" class="remove-btn" onclick="removeArrayElement('${elementId}')">✖</button>
                <input type="text" id="${elementId}_key" name="json_keys[]" placeholder="Ключ" value="${elementKey}" oninput="generateJSON()">
                <span> [ </span>
                <div id="${elementId}" class="json-array"></div>
                <div class="json-actions">
                    <button type="button" onclick="addElementToArray('${elementId}')">Добавить элемент</button>
                    <button type="button" onclick="addObjectToArray('${elementId}')">Добавить объект</button>
                </div>
                <span> ] </span>
            </div>
        `,
        object_not_input: `
            <div class="object_not_input-field">
                <button type="button" class="remove-btn" onclick="removeObjectNotInputElement('${elementId}')">✖</button>
                <span> { </span>
                <div id="${elementId}" class="json-object_not_input"></div>
                ${actionButtons(elementId)}
                <span> } </span>
            </div>
        `,
        element: `
            <div class="element-field" id="${elementId}">
                <button type="button" class="remove-btn" onclick="removeElement('${elementId}')">✖</button>
                <select id="${elementId}_type" name="json_types[]" oninput="generateJSON()">
                    <option value="string" ${inputType === "string" ? "selected" : ""}>string</option>
                    <option value="number" ${inputType === "number" ? "selected" : ""}>number</option>
                    <option value="boolean" ${inputType === "boolean" ? "selected" : ""}>boolean</option>
                    <option value="null" ${inputType === "null" ? "selected" : ""}>null</option>
                </select>
                <input type="text" id="${elementId}_value" name="json_values[]" placeholder="Значение" value="${value}" oninput="generateJSON()">
                <input type="button" value="..." onclick="BPAShowSelector('${elementId}_value', 'string', '');">
            </div>
        `
    };

    return $(templates[type]);
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


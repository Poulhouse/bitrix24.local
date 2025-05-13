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
    restoreParams();
}

// 🔥 Восстанавливает JSON из textarea при загрузке страницы
function restoreParams() {
    let queryString = $("#query_params").val().trim();
    let container = $("#json-builder");

    container.empty(); // Очищаем контейнер перед восстановлением

    if ($.trim(queryString) !== "") {
        try {
            let params = new URLSearchParams(queryString);
            restoreParamsRecursive(params, container.attr("id"));
        } catch (e) {
            console.error("❌ Ошибка парсинга QueryString:", e);
        }
    }
}

// 🔥 Рекурсивное восстановление JSON в виде полей
function restoreParamsRecursive(parsedParams, parentId) {
    parsedParams.forEach((value, key) => {
        let type = typeof value;
        console.log(`▶ Восстанавливаем: ${key} →`, value, `(тип: ${type}) в #${parentId}`);
        addParam(parentId, key, value, type);
    });
}

// 🔥 Генерирует JSON из полей
function generateQueryString() {
    let queryString = [];

    $("#json-builder .json-field").each(function() {
        let key = $(this).find('input[name="json_keys[]"]').val(); // Получаем ключ из label
        let value = $(this).find('input[name="json_values[]"]').val(); // Получаем значение из input

        if (key && value) {
            queryString.push(`${key}=${value}`);
        }
    });
    let result = queryString.join("&");
    $("#query_params").val(result);
    return result; // Возвращаем строку для отладки
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

// ========================
// 📌 Обработчики кликов (onclick) и ввода (oninput)
// ========================

// 🔥 Добавляет поле (ключ-значение)
function addParam(parentId = "json-builder", key = "", value = "", type = "string") {
    let fieldElement = createHtmlElement('field', key, parentId, value, type);
    $(`#${parentId}`).append(fieldElement);
    generateQueryString();
    return fieldElement.attr("id"); // Возвращаем ID
}

// 🔥 Удаляет элемент
function removeElement(elementId) {
    $(`#${elementId}`).remove();
    generateQueryString();
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


    let templates = {
        field: `
            <div class="json-field" id="${elementId}">
                <button type="button" class="remove-btn" onclick="removeElement('${elementId}')">✖</button>
                <input type="text" id="${elementId}_key" name="json_keys[]" placeholder="Ключ" value="${elementKey}" oninput="generateQueryString()">
                <select name="json_types[]" oninput="generateQueryString()">
                    <option value="string" ${inputType === "string" ? "selected" : ""}>string</option>
                    <option value="number" ${inputType === "number" ? "selected" : ""}>number</option>
                    <option value="boolean" ${inputType === "boolean" ? "selected" : ""}>boolean</option>
                    <option value="null" ${inputType === "null" ? "selected" : ""}>null</option>
                </select>
                <input type="text" id="${elementId}_value" name="json_values[]" placeholder="Значение" value="${value}" oninput="generateQueryString()">
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


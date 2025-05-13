<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die; ?>

<tr></tr>
</table>

<style>
    .request-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
    }

    .request-section {
        flex: 1;
        min-width: 60%;
        display: grid;
        justify-content: center;
        padding: 30px;
        background: rgba(0, 0, 0, .1);
        position: relative;
        height: 450px;
        overflow: auto;
        justify-items: center;
    }
    .request-section > .json-actions {
        margin: 8px 0px 8px 0px;
    }
    .request-section > .json-actions > button{
        background: none;
        border: 0;
        text-decoration: underline;
        font-size: 12px;
        padding: 0;
        color: #2067b0;
        cursor:pointer;
    }

    .json-builder-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .json-builder-container button {
        background: none;
        border: 0;
        text-decoration: underline;
        font-size: 12px;
        padding: 0;
        color: #2067b0;
        cursor:pointer;
    }
    .json-builder-container button.remove-btn {
        background: none;
        border: 0;
        text-decoration: none;
        font-size: 12px;
        padding: 0;
        color: #b02020;
        cursor:pointer;
    }
    .json-builder-container span {
        font-size: 16px;
        display: inline-flex;
        width: 12px;
        justify-content: flex-end;
    }
    .json-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 8px auto 8px 32px;
    }
    .json-object {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 8px auto 8px 24px;
        flex-direction: column;
    }
    .json-object_not_input {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 8px auto 8px 24px;
        flex-direction: column;
    }
    .json-array {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 8px auto 8px 24px;
        flex-direction: column;
    }
    .request_body {
        flex: 1;
        min-width: 20%;
    }
    .request_body .json-actions {
        display: flex;
        margin: 10px auto;
        flex-direction: row;
        align-content: center;
        justify-content: center;
    }
    .request_body button {
        width: max-content;
        font-size: 16px;
        padding: 11px 24px;
        background-color: darkorange;
        border: 0;
        border-radius: 4px;
        box-shadow: 1px 1px 6px 1px rgba(0, 0, 0, 0.5);
        cursor:pointer;
    }
    .request_body textarea {
        min-height: 400px;
        width: 100%;
        resize: none;
    }

    .request-settings {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-width: 20%;
        flex: 1;
    }

    .request-settings label {
        font-weight: bold;
    }

    .adm-workarea .request-settings input, .adm-workarea .request-settings select {
        width: 100%;
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box;
    }

    .request-settings button {
        width: auto;
        font-size: 16px;
        padding: 11px 24px;
        background-color: darkorange;
        border: 0;
        border-radius: 4px;
        box-shadow: 1px 1px 6px 1px rgba(0, 0, 0, 0.5);
        cursor: pointer;
    }

    .request-settings input#log {
        width: auto;
    }

    pre#response {
        display: block;
        min-height: 150px;
        background-color: #c6cdd3;
        margin: 0;
        border: 1px solid #aaa;
    }

    @media (max-width: 1100px) {
        .request-container {
            flex-direction: column;
        }
    }
</style>

<h2 style="color:#ff0000;text-align: center;">Для удобства увеличьте окно на весь экран!</h2>
<div class="request-container">
    <!-- Блок Настроек Запроса -->
    <div class="request-settings">
        <div id="url_row">
            <label for="url">URL запроса:</label>
            <input type="text" id="url" name="url" value="<?= htmlspecialcharsbx($arResult['arCurrentValues']["url"]) ?>">
        </div>

        <div id="method_row">
            <label for="method">Метод запроса:</label>
            <select id="method" name="method">
                <option value="POST" <?= ($arResult['arCurrentValues']["method"] == "POST" ? "selected" : "") ?>>POST</option>
                <option value="PUT" <?= ($arResult['arCurrentValues']["method"] == "PUT" ? "selected" : "") ?>>PUT</option>
                <option value="DELETE" <?= ($arResult['arCurrentValues']["method"] == "DELETE" ? "selected" : "") ?>>DELETE</option>
            </select>
        </div>

        <div id="content_type_row">
            <label for="content_type">Content-Type:</label>
            <select id="content_type" name="content_type">
                <option value="application/json">application/json</option>
                <option value="application/x-www-form-urlencoded">application/x-www-form-urlencoded</option>
            </select>
        </div>

        <div id="add_to_row">
            <label for="add_to">Куда добавить API Key:</label>
            <select id="add_to" name="add_to">
                <option value="" <?= ($arResult['arCurrentValues']["add_to"] == "" ? "selected" : "") ?>>Без авторизации</option>
                <option value="Header" <?= ($arResult['arCurrentValues']["add_to"] == "Header" ? "selected" : "") ?>>Header</option>
                <option value="Query" <?= ($arResult['arCurrentValues']["add_to"] == "Query" ? "selected" : "") ?>>Query</option>
            </select>
        </div>

        <div id="auth_key_row" style="display: none;">
            <label for="auth_key">Key:</label>
            <input type="text" id="auth_key" name="auth_key" value="<?= htmlspecialcharsbx($arResult['arCurrentValues']["auth_key"]) ?>">
        </div>

        <div id="auth_value_row" style="display: none;">
            <label for="auth_value">Value:</label>
            <input type="text" id="auth_value" name="auth_value" value="<?= htmlspecialcharsbx($arResult['arCurrentValues']["auth_value"]) ?>">
        </div>

        <div id="log_row">
            <label for="log">Логирование:</label>
            <input type="checkbox" id="log" name="log" value="Y" <?= ($arResult['arCurrentValues']["log"] == "Y" ? "checked" : "") ?>>
        </div>
    </div>

    <!-- Блок JSON-Редактора -->
    <div class="request-section">
        <div id="json-builder" class="json-builder-container"></div>
        <div class="json-actions">
            <button type="button" onclick="addField()">Добавить поле</button>
            <button type="button" id="arrayBtn" onclick="addArray()">Добавить массив</button>
            <button type="button" id="objectBtn" onclick="addObject()">Добавить объект</button>
        </div>
    </div>
    <div class="request_body">
        <textarea id="request_body" name="request_body"><?= htmlspecialcharsbx($arResult['arCurrentValues']["request_body"]) ?></textarea>
        <div class="json-actions">
            <button type="button" id="generateBtn" onclick="generateJSON()">Сформировать JSON</button>
            <button type="button" id="convertBtn" onclick="convertToFields()">Конвертировать</button>
        </div>
    </div>
</div>


<!--

<div id="id_row">
    <label for="document_id">ID документа:</label>
    <input type="text" id="document_id" name="document_id" value="{{ID}}">
</div>
<pre id="response">Результат запроса ...</pre>

<button type="button" onclick="sendTestRequest()">Сделать тестовый запрос</button>

-->



<?php
use Bitrix\Main\Page\Asset;
CJSCore::Init(['jquery', 'ajax']); // Подключаем jQuery и AJAX из Bitrix
Asset::getInstance()->addJs("/local/activities/kplabjsonbodyquery/scripts/script.js");
?>
<script>
    $(document).ready(function () {
        console.log("✅ JSON Editor загружен!");

        // 🔥 1. Обновляем видимость полей авторизации перед восстановлением JSON
        toggleAuthFields();

        // 🔥 2. Восстанавливаем JSON из request_body
        restoreJsonFields();

        // 🔥 3. Гарантируем, что DOM синхронизирован с JSON (НО вызываем только один раз!)
        //convertToFields();

        // 🔥 4. Настраиваем MutationObserver с debounce
        /*let debounceTimer;
        let observer = new MutationObserver(() => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                generateJSON();
            }, 300); // ⏳ 300 мс задержка для предотвращения лишних вызовов
        });*/

        /*observer.observe(document.getElementById("json-builder"), {
            childList: true,
            subtree: true
        });*/

        // 🔥 5. Обновляем JSON при клике на кнопку генерации
        $("#generateBtn").click(() => {
            generateJSON();
        });

        // 🔥 6. Пересчитываем JSON при изменении метода запроса
        $("#method").change(updateRequestBodyLabel);
        $("#add_to").change(toggleAuthFields);
    });
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
    function updateRequestBodyLabel() {
        let objectBtn = $("#objectBtn");
        let arrayBtn = $("#arrayBtn");

        //generateBtn.attr("onclick", `generate()`);
        generateJSON();

        objectBtn.show();
        arrayBtn.show();
    }
</script>

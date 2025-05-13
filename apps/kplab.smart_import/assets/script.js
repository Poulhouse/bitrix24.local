document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("importForm");

    form.addEventListener("submit", function (event) {
        event.preventDefault();

        let formData = new FormData(form);
        let fileInput = form.querySelector('input[name="file"]');

        // Получаем значения DOMAIN, LANG, PROTOCOL, APP_SID
        const domain = form.querySelector('input[name="DOMAIN"]').value;
        const lang = form.querySelector('input[name="LANG"]').value;
        const protocol = form.querySelector('input[name="PROTOCOL"]').value;
        const appSid = form.querySelector('input[name="APP_SID"]').value;

        // Создаем query-строку
        const queryParams = new URLSearchParams({
            DOMAIN: domain,
            LANG: lang,
            PROTOCOL: protocol,
            APP_SID: appSid
        }).toString();

        if (fileInput.files.length === 0) {
            alert("Выберите файл для импорта");
            return;
        }

        fetch(`handler.php?${queryParams}`, {
            method: "POST",
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const importContainer = document.querySelector('.import-container');
                    const mappingContainer = document.querySelector('.mapping-container');
                    mappingContainer.style.display = 'block';
                    importContainer.style.display = 'none';
                    showMappingPage(data.data, queryParams); // Отображаем страницу сопоставления
                    alert("Файл загружен успешно! Поля: " + data.data.columns.join(", "));
                } else {
                    alert("Ошибка: " + data.message);
                }
            })
            .catch(error => {
                console.error("Ошибка запроса:", error);
                alert("Ошибка загрузки файла");
            });
    });
});


function showMappingPage(data, queryParams) {
    const mappingForm = document.getElementById('mappingForm');
    const mappingFormFields = document.getElementById('mappingFormFields');
    mappingFormFields.innerHTML = ''; // Очистка предыдущих полей

    const columns = data.columns;
    const fields = data.fields;
    const entityTypeId = data.entityTypeId;
    const filePath = data.filePath;

    let inputEntityTypeId;
    inputEntityTypeId = document.createElement("input");
    inputEntityTypeId.type = "hidden";
    inputEntityTypeId.id = 'entityTypeId';
    inputEntityTypeId.name = 'entityTypeId';
    inputEntityTypeId.value = entityTypeId;

    let inputPath;
    inputPath = document.createElement("input");
    inputPath.type = "hidden";
    inputPath.id = 'filePath';
    inputPath.name = 'filePath';
    inputPath.value = filePath;

    for (const [fieldName, fieldInfo] of Object.entries(fields)) {
        // Исключаем поле "id" из обработки
        if (fieldName === "id") {
            continue; // Пропускаем это поле
        }
        const fieldGroup = document.createElement('div');
        fieldGroup.className = 'form-group';

        // Создаем метку для поля
        const label = document.createElement("label");
        label.textContent = fieldInfo.title || fieldName;

        // Создаем элемент управления на основе типа поля
        /*let input;
        switch (fieldInfo.type) {
            case "string":
            case "text":
                input = document.createElement("input");
                input.type = "text";
                break;
            case "integer":
                input = document.createElement("input");
                input.type = "number";
                break;
            case "boolean":
                input = document.createElement("input");
                input.type = "checkbox";
                break;
            case "datetime":
                input = document.createElement("input");
                input.type = "datetime-local";
                break;
            case "date":
                input = document.createElement("input");
                input.type = "date";
                break;
            case "enumeration":
                input = document.createElement("select");
                if (fieldInfo.items) {
                    fieldInfo.items.forEach(item => {
                        const option = document.createElement("option");
                        option.value = item.ID;
                        option.textContent = item.VALUE;
                        input.appendChild(option);
                    });
                }
                break;
            case "file":
                input = document.createElement("input");
                input.type = "file";
                break;
            default:
                input = document.createElement("input");
                input.type = "text";
        }

        input.name = fieldName;*/

        // Создаем выпадающий список для сопоставления столбцов
        const select = document.createElement("select");
        select.className = 'column-select';
        select.name = fieldName;

        const optionNotMatch = document.createElement('option');
        optionNotMatch.value = '';
        optionNotMatch.textContent = 'Не сопоставлять';
        select.appendChild(optionNotMatch);

        columns.forEach(column => {
            const option = document.createElement('option');
            option.value = column;
            option.textContent = column;
            select.appendChild(option);
        });

        // Добавляем метку, элемент управления и выпадающий список в контейнер
        fieldGroup.appendChild(inputEntityTypeId);
        fieldGroup.appendChild(inputPath);
        fieldGroup.appendChild(label);
        fieldGroup.appendChild(select);
        mappingFormFields.appendChild(fieldGroup);
    }

    mappingForm.addEventListener('submit', function(event) {
        event.preventDefault();
        let formData2 = new FormData(mappingForm);
        const mappings = {};
        const selects = document.querySelectorAll('.column-select');
        selects.forEach(select => {
            mappings[select.name] = select.value;
        });
        console.log(mappings);

        // Получаем элемент загрузчика
        const loader = document.getElementById('loader');
        const mappingContainer = document.querySelector('.mapping-container');
        const successContainer = document.getElementById('success-container');

        fetch(`handler.php?${queryParams}`, {
            method: 'POST',
            body: formData2
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                successContainer.style.display = 'block';
            } else {
                alert('Ошибка при импорте: ' + data.message);
            }
        })
        .catch(error => {
            console.error("Ошибка запроса:", error);
            alert("Ошибка отправки сопоставлений");
        })
        .finally(() => {
            // Скрываем загрузчик после завершения запроса
            loader.style.display = 'none';
        });

        // Показываем загрузчик перед отправкой запроса
        loader.style.display = 'block';
        mappingContainer.style.display = 'none';
    });
}
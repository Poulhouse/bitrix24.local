</div>


<script>
    document.addEventListener("DOMContentLoaded", function() {

        function getRowsToMerge(rows, className) {
            let lastValue = null;
            let rowspan = 0;
            let mergeInfo = [];

            for (let i = 0; i < rows.length; i++) {
                let cell = rows[i].querySelector("." + className);
                if (cell) {
                    let currentValue = cell.innerHTML;
                    if (currentValue === lastValue && currentValue !== "") {
                        rowspan++;
                    } else {
                        if (lastValue !== null && lastValue !== "") {
                            mergeInfo.push({ row: i - rowspan - 1, rowspan: rowspan + 1 });
                        }
                        lastValue = currentValue;
                        rowspan = 0;
                    }
                }
            }

            if (rowspan > 0 && lastValue !== null && lastValue !== "") {
                mergeInfo.push({ row: rows.length - rowspan - 1, rowspan: rowspan + 1 });
            }

            return mergeInfo;
        }

        function applyMerge(rows, className, mergeInfo) {
            for (let i = 0; i < mergeInfo.length; i++) {
                let row = rows[mergeInfo[i].row];
                let cell = row.querySelector("." + className);
                if (cell) {
                    cell.rowSpan = mergeInfo[i].rowspan;
                    for (let j = 1; j < mergeInfo[i].rowspan; j++) {
                        let nextRow = rows[mergeInfo[i].row + j];
                        let cellToRemove = nextRow.querySelector("." + className);
                        if (cellToRemove) {
                            cellToRemove.remove();
                        }
                    }
                }
            }
        }

        function applyMergeWithinProduct(rows, mergeInfoProduct, mergeInfoDealGroup) {
            // Пройдемся по каждому блоку строк, объединенных по продукту
            for (let i = 0; i < mergeInfoProduct.length; i++) {
                let productStart = mergeInfoProduct[i].row;  // Начало блока по продукту
                let productEnd = productStart + mergeInfoProduct[i].rowspan;  // Конец блока по продукту

                // Внутри этого блока нужно схлопнуть по признаку сделки (priznakDealGroup)
                let currentDealGroup = null;
                let rowspan = 0;
                let dealGroupMergeInfo = [];

                for (let j = productStart; j < productEnd; j++) {
                    let row = rows[j];
                    let dealGroupCell = row.querySelector(".priznakDealGroup");

                    if (dealGroupCell) {
                        let currentValue = dealGroupCell.innerHTML;

                        // Если значение такое же, увеличиваем rowspan
                        if (currentValue === currentDealGroup && currentValue !== "") {
                            rowspan++;
                        } else {
                            // Если значение изменилось, фиксируем предыдущую группу
                            if (currentDealGroup !== null && rowspan > 0) {
                                dealGroupMergeInfo.push({ row: j - rowspan - 1, rowspan: rowspan + 1 });
                            }

                            // Начинаем новую группу
                            currentDealGroup = currentValue;
                            rowspan = 0;
                        }
                    }
                }

                // Добавляем информацию для последней группы
                if (currentDealGroup !== null && rowspan > 0) {
                    dealGroupMergeInfo.push({ row: productEnd - rowspan - 1, rowspan: rowspan + 1 });
                }

                // Применяем объединение для найденных блоков по признаку сделки
                applyMerge(rows, "priznakDealGroup", dealGroupMergeInfo);
                applyMerge(rows, "pd_productsCurrentTons", dealGroupMergeInfo);
            }
        }


        function mergeTable(table) {
            let rows = Array.from(table.rows);
            let companyRows = [];
            let currentCompany = null;

            rows.forEach(row => {
                let companyCell = row.querySelector(".company");
                if (companyCell) {
                    let companyValue = companyCell.innerHTML;
                    if (companyValue !== currentCompany) {
                        if (currentCompany !== null) {
                            processCompanyRows(companyRows);
                        }
                        currentCompany = companyValue;
                        companyRows = [];
                    }
                }
                companyRows.push(row);
            });

            if (companyRows.length > 0) {
                processCompanyRows(companyRows);
            }
        }

        // Обработка строк по каждой компании/товару/сегменту
        function processCompanyRows(rows) {
            let classesToMergeProduct = [
                "product", "product-segment", "annual-need",
                "p_productsCurrentTons", "planCurrentYear",
                "p_productsPrevTons", "procentPlana"
            ];

            // Схлопываем по компании
            let mergeInfoCompany = getRowsToMerge(rows, "company");
            applyMerge(rows, "company", mergeInfoCompany);
            applyMerge(rows, "segment", mergeInfoCompany);
            applyMerge(rows, "endActive", mergeInfoCompany);
            applyMerge(rows, "contractHaving", mergeInfoCompany);
            applyMerge(rows, "classificate", mergeInfoCompany);
            applyMerge(rows, "assigned", mergeInfoCompany);

            // Схлопываем по продукту
            let mergeInfoProduct = getRowsToMerge(rows, "product");
            for (let className of classesToMergeProduct) {
                applyMerge(rows, className, mergeInfoProduct);
            }

            // Собираем информацию для объединения по признаку сделки
            let mergeInfoDealGroup = getRowsToMerge(rows, "priznakDealGroup");
            // Схлопывание по признаку сделки внутри каждого блока продукта
            applyMergeWithinProduct(rows, mergeInfoProduct, mergeInfoDealGroup);
        }

        // Функция для обновления таблицы на основе данных
        function updateTable(data) {
            let table = document.getElementById('myTableOtchet');

            // Очистка существующих данных таблицы, кроме заголовка
            while (table.rows.length > 1) {
                table.deleteRow(1);
            }

            // Добавление новых строк на основе данных с сервера
            data.forEach(row => {
                let newRow = table.insertRow();

                newRow.insertCell().innerText = row.CLIENT_SEGMENT;
                newRow.insertCell().innerText = row.END_ACTIVE;
                newRow.insertCell().innerText = row.CONTRACT_HAVING;
                newRow.insertCell().innerHTML = `<a target="_blank" href="https://bt.rosma.ru/crm/company/details/${row.COMPANY_ID}/">${row.COMPANY_TITLE}</a>`;
                newRow.insertCell().innerText = row.ASSIGNED;
                newRow.insertCell().innerText = row.PRODUCT_NAME;
                newRow.insertCell().innerText = row.SEGMENT_NAME;
                newRow.insertCell().innerText = row.YEAR_REQUIREMENT;
                newRow.insertCell().innerText = row.FACT_BY_SECTION_ID;  // Здесь уже будет итоговая сумма
                newRow.insertCell().innerText = row.PLAN_FOR_CURRENT_YEAR;
                newRow.insertCell().innerText = row.FACT_FOR_LAST_YEAR;
                newRow.insertCell().innerText = row.PERCENT_PLAN_COMPLETION;
                newRow.insertCell().innerText = row.FACT_BY_DEAL;
                newRow.insertCell().innerText = row.DEAL_SIGN;
                newRow.insertCell().innerHTML = `<a target="_blank" href="https://bt.rosma.ru/crm/deal/details/${row.DEAL_ID}/">${row.DEAL_TITLE}</a>`;
            });

            mergeTable(table);
        }

        let table = document.getElementById("myTableOtchet");
        let tableToExcel = export_Excel_noGroup('myTableOtchet', 'Отчет');

        // Вызываем функцию для схлопывания строк
        mergeTable(table);

        document.getElementById('filterButton').addEventListener('click', function() {
            document.getElementById('overlay').style.display = 'block';
            document.getElementById('filterPopup').style.display = 'block';
        });

        document.getElementById('closePopupButton').addEventListener('click', function() {
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('filterPopup').style.display = 'none';
        });

        document.getElementById('applyFilterButton').addEventListener('click', function() {
            document.getElementById('filterPopup').style.display = 'none';
        });
        function export_Excel(tableId, name, fileName) {
            let table = document.getElementById(tableId);

            if (!table || table.rows.length === 0) {
                alert('Сначала сформируйте отчет');
                return;
            }

            let downloadURI = function(uri, name) {
                let link = document.createElement("a");
                link.download = name;
                link.href = uri;
                link.click();
            }

            let tableToExcel = (function() {
                let uri = 'data:application/vnd.ms-excel;base64,',
                    template = `
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head>
                    <meta charset="UTF-8">
                    <style>
                        .excel-table {
                            border-collapse: collapse;
                            width: 100%;
                        }
                        .excel-table th, .excel-table td {
                            border: 1px solid black;
                            vertical-align: middle;
                            text-align: center;
                            padding: 5px;
                        }
                    </style>
                </head>
                <body>
                    <table class="excel-table">{table}</table>
                </body>
                </html>`,
                    base64 = function(s) { return window.btoa(unescape(encodeURIComponent(s))) },
                    format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; }) }

                let ctx = {
                    worksheet: name || 'Worksheet',
                    table: table.innerHTML
                };

                return uri + base64(format(template, ctx));
            })();

            downloadURI(tableToExcel, fileName);
        }

        function export_Excel_noGroup(tableId, name) {
            let table = document.getElementById(tableId);

            if (!table || table.rows.length === 0) {
                alert('Сначала сформируйте отчет');
                return;
            }

            let tableToExcel = (function() {
                let uri = 'data:application/vnd.ms-excel;base64,',
                    template = `
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head>
                    <meta charset="UTF-8">
                    <style>
                        .excel-table {
                            border-collapse: collapse;
                            width: 100%;
                        }
                        .excel-table th, .excel-table td {
                            border: 1px solid black;
                            vertical-align: middle;
                            text-align: center;
                            padding: 5px;
                        }
                    </style>
                </head>
                <body>
                    <table class="excel-table">{table}</table>
                </body>
                </html>`,
                    base64 = function(s) { return window.btoa(unescape(encodeURIComponent(s))) },
                    format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; }) }

                let ctx = {
                    worksheet: name || 'Worksheet',
                    table: table.innerHTML
                };

                return uri + base64(format(template, ctx));
            })();

            return tableToExcel;
        }

        document.getElementById('exportExcelButton').addEventListener('click', function() {
            export_Excel('myTableOtchet', 'Отчет', 'Отчет.xls');
        });
        document.getElementById('exportExcelNoGroupButton').addEventListener('click', function() {
            let downloadURI = function(uri, name) {
                let link = document.createElement("a");
                link.download = name;
                link.href = uri;
                link.click();
            }
            downloadURI(tableToExcel, 'Отчет_План-Факт_Без_Группировки.xls');
        });

        document.getElementById('dealSignFilter').addEventListener('change', function() {
            // Получаем выбранное значение
            var dealSignFilterValue = this.value;

            // Получаем чекбокс
            var showDealSignDetail = document.getElementById('showDealSignDetail');

            // Проверяем, выбрано ли значение (не пустое) и устанавливаем чекбокс
            if (dealSignFilterValue) {
                showDealSignDetail.checked = true;
            } else {
                showDealSignDetail.checked = false;
            }
        });
    });
</script>

<script>
    BX24.init(function(){
        console.log('Bitrix24 API инициализировано'); // Проверяем инициализацию BX24 API
        // Получаем высоту окна браузера
        var windowHeight = window.innerHeight;
        var windowWidth = window.innerWidth;
        var contentHeight = document.body.scrollHeight;
        console.log('windowWidth ', windowWidth); // windowWidth
        console.log('windowHeight ', windowHeight); // windowHeight
        console.log('contentHeight ', contentHeight); // contentHeight

        // Устанавливаем размеры окна для приложения в Bitrix24
        BX24.resizeWindow(windowWidth, Math.max(contentHeight, 1000));

        console.log('Начинаем вызов BX24.placement.info()'); // Добавлено для диагностики

        BX24.placement.info(function(info) {
            console.log('Placement info вызвано'); // Проверяем, был ли вызван info

            if (!info) {
                console.error('Placement info пустое или недоступно');
                return;
            }

            console.log(info); // Логируем результат info для диагностики

            // Проверяем, открыто ли приложение в карточке компании
            if (info.placement === 'CRM_COMPANY_DETAIL_TAB') {
                console.log('Приложение открыто в карточке компании');

                var companyTitle = info.options.TITLE; // Получаем название компании
                var companyId = info.options.ID; // Получаем ID компании
				<?php
	            $host = $_SERVER['HTTP_HOST'];
	            $handlerDir = '/local/reportPlanFact/handlers';
				?>
                // Редирект на PHP с параметром client
                window.location.href = 'https://<?=$_SERVER['HTTP_HOST']?>/local/reportPlanFact/handlers/index.php?client=' + encodeURIComponent(companyTitle);
            } else {
                
                console.log('Приложение не открыто в карточке компании');
                console.log('Current placement:', info.placement);
            }
        });
    });
</script>
</body>
</html>
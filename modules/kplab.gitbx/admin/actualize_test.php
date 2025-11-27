<?php
// /local/modules/kplab.gitbx/admin/actualize_test.php

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;
use KPLab\GitBx\Config\Env;
use KPLab\GitBx\Config\ModuleSettings;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

$moduleId = 'kplab.gitbx';

if (!Loader::includeModule($moduleId)) {
    $APPLICATION->AuthForm("Не удалось загрузить модуль {$moduleId}");
}

$POST_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if ($POST_RIGHT < "W") {
    $APPLICATION->AuthForm("Доступ запрещён");
}

/**
 * ============================================
 *  ВАЖНО: ЭТА СТРАНИЦА МОЖЕТ РАБОТАТЬ ТОЛЬКО НА PROD
 * ============================================
 */
// Страница только для PROD
if (!Env::isProd()) {
    $APPLICATION->SetTitle("GitBx — Актуализация тестового портала");
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
    ShowError("Страница доступна только в окружении «Боевой» (env = prod).");
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
    return;
}

$APPLICATION->SetTitle("GitBx — Актуализация тестового портала");

// UI-компоненты Bitrix
Extension::load(['ui.buttons', 'ui.notification']);

$dirSnapshots = rtrim(ModuleSettings::dirSnapshots(), '/');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
?>
    <style>
        .gitbx-layout {
            display: flex;
            max-width: 1200px;
        }
        .gitbx-left {
            width: 260px;
            background: #e7f5ff;
            padding: 10px;
            border-right: 1px solid #d3d3d3;
        }
        .gitbx-left h2 {
            font-size: 16px;
            margin-top: 0;
        }
        .gitbx-step {
            padding: 8px 10px;
            margin-bottom: 4px;
            cursor: pointer;
            border-radius: 3px;
        }
        .gitbx-step:hover {
            background: #d0ebff;
        }
        .gitbx-step.gitbx-step-active {
            background: #1971c2;
            color: #fff;
        }

        .gitbx-right {
            flex: 1;
            padding: 10px 15px;
        }
        .gitbx-right-tabs {
            margin-bottom: 8px;
        }
        .gitbx-tab {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-bottom: none;
            margin-right: 3px;
            cursor: pointer;
            border-radius: 4px 4px 0 0;
            background: #f1f3f5;
            font-size: 12px;
        }
        .gitbx-tab.gitbx-tab-active {
            background: #fff;
            border-bottom-color: #fff;
            font-weight: bold;
        }
        .gitbx-right-body {
            border: 1px solid #ccc;
            padding: 0;
            background: #fff;
        }
        #gitbx-log {
            margin: 0;
            padding: 10px;
            max-height: 550px;
            overflow: auto;
            font-family: monospace;
            font-size: 12px;
        }
        #gitbx-diff {
            height: 550px;
            display: none;
        }
        .gitbx-status-line {
            margin-top: 6px;
            font-size: 12px;
            color: #555;
        }
    </style>

    <div class="gitbx-layout">
        <!-- Левая колонка: шаги -->
        <div class="gitbx-left">
            <h2>Шаги актуализации</h2>

            <div class="gitbx-step" data-step="snapshot_test">
                1. Сделать snapshot TEST
            </div>
            <div class="gitbx-step" data-step="snapshot_prod">
                2. Сделать snapshot PROD
            </div>
            <div class="gitbx-step" data-step="compare">
                3. Сравнить снимки (TEST vs PROD)
            </div>
            <div class="gitbx-step" data-step="build_package">
                4. Подготовить migration package
            </div>
            <div class="gitbx-step" data-step="send_apply">
                5. Отправить и применить на TEST
            </div>

            <div class="gitbx-status-line" id="gitbx-snapshot-status">
                Snapshot-файлы: не проверено
            </div>

            <div style="margin-top:10px;">
                <a id="btn_snapshot_test"
                   href="/local/modules/kplab.gitbx/tools/download.php?type=snapshot_test"
                   class="ui-btn ui-btn-success ui-btn-sm"
                   style="display:none; margin-bottom:4px;">
                    Скачать snapshot TEST
                </a><br>
                <a id="btn_snapshot_prod"
                   href="/local/modules/kplab.gitbx/tools/download.php?type=snapshot_prod"
                   class="ui-btn ui-btn-success ui-btn-sm"
                   style="display:none;">
                    Скачать snapshot PROD
                </a>
            </div>
        </div>

        <!-- Правая колонка: результаты -->
        <div class="gitbx-right">
            <div class="gitbx-right-tabs">
                <span class="gitbx-tab gitbx-tab-active" data-tab="test">Snapshot TEST</span>
                <span class="gitbx-tab" data-tab="prod">Snapshot PROD</span>
                <span class="gitbx-tab" data-tab="diff">Diff (PROD → TEST)</span>
                <span class="gitbx-tab" data-tab="merge">Mergefile / Package</span>
                <span class="gitbx-tab" data-tab="apply">Результат применения</span>
            </div>

            <div class="gitbx-right-body">
                <pre id="gitbx-log"></pre>
                <div id="gitbx-diff"></div>
            </div>
        </div>
    </div>

    <!-- Monaco editor (через loader.js CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs/loader.js"></script>
    <script>
        // Конфиг пути к Monaco
        require.config({
            paths: { 'vs': 'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs' }
        });
    </script>

    <script>
        BX.ready(function () {

            const steps   = document.querySelectorAll('.gitbx-step');
            const tabs    = document.querySelectorAll('.gitbx-tab');
            const logBox  = BX('gitbx-log');
            const diffBox = BX('gitbx-diff');
            const btnSnapTest = BX('btn_snapshot_test');
            const btnSnapProd = BX('btn_snapshot_prod');
            const snapStatus  = BX('gitbx-snapshot-status');

            // Глобальное состояние страницы
            const gitbxState = {
                snapshotTest: null,
                snapshotProd: null,
                mergePackage: null,
                applyResult: null
            };

            let monacoLoaded = false;
            let diffEditor   = null;

            function setStepActive(stepCode) {
                steps.forEach(function (el) {
                    if (el.getAttribute('data-step') === stepCode) {
                        el.classList.add('gitbx-step-active');
                    } else {
                        el.classList.remove('gitbx-step-active');
                    }
                });
            }

            function setTabActive(tabCode) {
                tabs.forEach(function (el) {
                    if (el.getAttribute('data-tab') === tabCode) {
                        el.classList.add('gitbx-tab-active');
                    } else {
                        el.classList.remove('gitbx-tab-active');
                    }
                });

                if (tabCode === 'diff') {
                    logBox.style.display  = 'none';
                    diffBox.style.display = 'block';
                } else {
                    diffBox.style.display = 'none';
                    logBox.style.display  = 'block';

                    switch (tabCode) {
                        case 'test':
                            renderJson(gitbxState.snapshotTest, 'Snapshot TEST не получен.');
                            break;
                        case 'prod':
                            renderJson(gitbxState.snapshotProd, 'Snapshot PROD не получен.');
                            break;
                        case 'merge':
                            renderJson(gitbxState.mergePackage, 'Migration package ещё не сформирован.');
                            break;
                        case 'apply':
                            renderJson(gitbxState.applyResult, 'Применение ещё не запускалось.');
                            break;
                    }
                }
            }

            function setLoading(on) {
                steps.forEach(function (el) {
                    if (on) {
                        el.classList.add('ui-btn-wait');
                        el.style.pointerEvents = 'none';
                    } else {
                        el.classList.remove('ui-btn-wait');
                        el.style.pointerEvents = '';
                    }
                });
            }

            function renderJson(data, emptyText) {
                if (!data) {
                    logBox.textContent = emptyText || '';
                } else {
                    logBox.textContent = JSON.stringify(data, null, 2);
                }
            }

            function notify(text) {
                if (BX && BX.UI && BX.UI.Notification && BX.UI.Notification.Center) {
                    BX.UI.Notification.Center.notify({ content: text });
                } else {
                    alert(text);
                }
            }

            function updateSnapshotButtons(statusData) {
                if (!statusData) return;

                if (statusData.test) {
                    btnSnapTest.style.display = 'inline-block';
                }
                if (statusData.prod) {
                    btnSnapProd.style.display = 'inline-block';
                }

                snapStatus.textContent =
                    'Snapshot-файлы: TEST=' + (statusData.test ? 'есть' : 'нет') +
                    ', PROD=' + (statusData.prod ? 'есть' : 'нет');
            }

            function checkSnapshotFiles() {
                BX.ajax.runAction('kplab:gitbx.Snapshot.status').then(function (response) {
                    updateSnapshotButtons(response.data || {});
                }, function () {
                    snapStatus.textContent = 'Snapshot-файлы: статус получить не удалось';
                });
            }

            /**
             * 1. Сделать snapshot TEST (берём с удалённого TEST портала)
             */
            function stepSnapshotTest() {
                setStepActive('snapshot_test');
                setTabActive('test');
                setLoading(true);
                logBox.textContent = 'Получаем snapshot TEST...';

                BX.ajax.runAction('kplab:gitbx.Snapshot.get', {
                    data: { target: 'test' }
                }).then(function (response) {
                    setLoading(false);

                    if (response.status !== 'success') {
                        logBox.textContent = 'Ошибка: ' + JSON.stringify(response, null, 2);
                        return;
                    }

                    const data = response.data || {};
                    gitbxState.snapshotTest = data.data || data;

                    renderJson(gitbxState.snapshotTest);
                    notify('Snapshot TEST получен');
                }, function (error) {
                    setLoading(false);
                    logBox.textContent = 'AJAX error: ' + JSON.stringify(error, null, 2);
                });
            }

            /**
             * 2. Сделать snapshot PROD (локальный портал)
             */
            function stepSnapshotProd() {
                setStepActive('snapshot_prod');
                setTabActive('prod');
                setLoading(true);
                logBox.textContent = 'Получаем snapshot PROD...';

                BX.ajax.runAction('kplab:gitbx.Snapshot.get', {
                    data: { target: 'local' }
                }).then(function (response) {
                    setLoading(false);

                    if (response.status !== 'success') {
                        logBox.textContent = 'Ошибка: ' + JSON.stringify(response, null, 2);
                        return;
                    }

                    const data = response.data || {};
                    gitbxState.snapshotProd = data.data || data;

                    renderJson(gitbxState.snapshotProd);
                    notify('Snapshot PROD получен');
                }, function (error) {
                    setLoading(false);
                    logBox.textContent = 'AJAX error: ' + JSON.stringify(error, null, 2);
                });
            }

            /**
             * 3. Сравнение снимков (Monaco diff: PROD → TEST)
             */
            function stepCompare() {
                setStepActive('compare');
                setTabActive('diff');

                if (!gitbxState.snapshotTest || !gitbxState.snapshotProd) {
                    logBox.style.display = 'block';
                    diffBox.style.display = 'none';
                    logBox.textContent = 'Нужно сначала получить snapshot TEST и PROD.';
                    return;
                }

                // ВАЖНО: слева TEST (текущее), справа PROD (цель)
                const originalText = JSON.stringify(gitbxState.snapshotTest, null, 2); // TEST
                const modifiedText = JSON.stringify(gitbxState.snapshotProd, null, 2); // PROD

                function initDiff() {
                    if (!diffEditor) {
                        diffEditor = monaco.editor.createDiffEditor(diffBox, {
                            readOnly: true,
                            automaticLayout: true,
                            originalEditable: false
                        });
                    }

                    const originalModel = monaco.editor.createModel(originalText, 'json');
                    const modifiedModel = monaco.editor.createModel(modifiedText, 'json');

                    diffEditor.setModel({
                        original: originalModel,
                        modified: modifiedModel
                    });
                }

                if (monacoLoaded) {
                    diffBox.style.display = 'block';
                    logBox.style.display  = 'none';
                    initDiff();
                } else {
                    diffBox.style.display = 'block';
                    logBox.style.display  = 'none';
                    diffBox.innerHTML = 'Загрузка Monaco editor...';

                    require(['vs/editor/editor.main'], function () {
                        monacoLoaded = true;
                        diffBox.innerHTML = '';
                        initDiff();
                    });
                }
            }

            /**
             * 4. Подготовить migration package (используем экспорт на TEST с mode=build_package)
             */
            function stepBuildPackage() {
                setStepActive('build_package');
                setTabActive('merge');
                setLoading(true);
                logBox.textContent = 'Формируем migration package...';

                BX.ajax.runAction('kplab:gitbx.Migration.exporttotest', {
                    data: { mode: 'build_package' }
                }).then(function (response) {
                    setLoading(false);

                    if (response.status !== 'success') {
                        logBox.textContent = 'Ошибка: ' + JSON.stringify(response, null, 2);
                        return;
                    }

                    const data   = response.data || {};
                    const result = data.result || {};

                    // ожидаем, что backend положит package в result.package
                    gitbxState.mergePackage = result.package || result;

                    renderJson(gitbxState.mergePackage, 'Package пустой или не вернулся.');

                    notify('Migration package сформирован');
                }, function (error) {
                    setLoading(false);
                    logBox.textContent = 'AJAX error: ' + JSON.stringify(error, null, 2);
                });
            }

            /**
             * 5. Отправить и применить на TEST (mode=send_to_test / full)
             * Для MVP используем mode='send_to_test', если он есть; иначе 'full'.
             */
            function stepSendApply() {
                setStepActive('send_apply');
                setTabActive('apply');
                setLoading(true);
                logBox.textContent = 'Отправляем и применяем миграцию на TEST...';

                BX.ajax.runAction('kplab:gitbx.Migration.exporttotest', {
                    data: { mode: 'send_to_test' }
                }).then(function (response) {
                    setLoading(false);

                    if (response.status !== 'success') {
                        logBox.textContent = 'Ошибка: ' + JSON.stringify(response, null, 2);
                        return;
                    }

                    const data   = response.data || {};
                    const result = data.result || {};

                    gitbxState.applyResult = result;

                    renderJson(gitbxState.applyResult, 'Ответ TEST портала пустой.');

                    notify('Миграция отправлена и применена на TEST');
                }, function (error) {
                    setLoading(false);
                    logBox.textContent = 'AJAX error: ' + JSON.stringify(error, null, 2);
                });
            }

            // Привязка шагов
            steps.forEach(function (el) {
                const stepCode = el.getAttribute('data-step');
                BX.bind(el, 'click', function () {
                    switch (stepCode) {
                        case 'snapshot_test':
                            stepSnapshotTest();
                            break;
                        case 'snapshot_prod':
                            stepSnapshotProd();
                            break;
                        case 'compare':
                            stepCompare();
                            break;
                        case 'build_package':
                            stepBuildPackage();
                            break;
                        case 'send_apply':
                            stepSendApply();
                            break;
                    }
                });
            });

            // Переключение табов
            tabs.forEach(function (el) {
                const tabCode = el.getAttribute('data-tab');
                BX.bind(el, 'click', function () {
                    setTabActive(tabCode);
                });
            });

            // Стартовое состояние
            setTabActive('test');
            checkSnapshotFiles();
        });
    </script>

<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");

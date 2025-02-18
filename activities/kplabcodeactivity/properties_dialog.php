<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<script>
    BX.ready(function() {
        if (!top.BXCodeEditors)
            top.BXCodeEditors = window.BXCodeEditors = {};

        function codeEditorLoaded() {
            var CE = new window.JCCodeEditor({
                'id': 'bxce_editor',
                'textareaId': 'execute_code',
                'theme': 'dark',
                'highlightMode': true,
                'saveSettings': true,
                'resized': true,
                'forceSyntax': 'php'
            }, {
                'GoToLine': 'Быстрый переход на строку',
                'Line': 'строка',
                'Char': 'символ',
                'Total': 'Всего',
                'Lines': 'строк',
                'Chars': 'символов',
                'LineTitle': 'Текущая строка',
                'CharTitle': 'Текущий символ',
                'EnableHighlight': 'подсветка синтаксиса',
                'EnableHighlightTitle': 'Включить/выключить подсветку синтаксиса',
                'DarkTheme': 'темный фон',
                'LightTheme': 'светлый фон',
                'HighlightWrongwarning': 'В текущем браузере подсветка синтаксиса может работать некорректно.'
            });

            top.BXCodeEditors['bxce_editor'] = CE;
            BX.onCustomEvent(window, "OnCodeEditorReady", ['bxce_editor']);
        }

        if (!window.JCCodeEditor) {
            BX.loadScript('/bitrix/js/fileman/code_editor/code-editor.js', codeEditorLoaded);
            BX.loadCSS('/local/activities/kplabcodeactivity/assets/css/code-editor.css');
        } else {
            codeEditorLoaded();
        }
    });
</script>
<style>
    .bxce.bxce-hls {
        width: 100% !important;
    }
</style>
<td align="right" width="40%"><span class="adm-required-field"><span style="color:#FF0000;">*</span><?= GetMessage("BPCA_PD_PHP") ?>:</span></td>
<td width="60%">
    <textarea id="execute_code" name="execute_code" style="width:100%; display:none;"><?= htmlspecialcharsbx($arCurrentValues['execute_code']) ?></textarea>
    <!--<div id="bxce_editor" style="height: 350px;">
        <?//= htmlspecialcharsbx($arCurrentValues['execute_code']) ?>
    </div>-->
<!--    <input type="button" value="..." onclick="BPAShowSelector('execute_code', 'string');">-->
</td>



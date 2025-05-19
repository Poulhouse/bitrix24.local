<?php
include($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
require_once ('crest/crest.php');
require_once ('crest/settings.php');
$host = $_SERVER['HTTP_HOST'];
$handlerDir = '/local/reportPlanFact/handlers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<script src="//api.bitrix24.com/api/v1/"></script>
	<script>BX24.init();</script>
	<style>
        html, body, div, span, applet, object, iframe,
        h1, h2, h3, h4, h5, h6, p, blockquote, pre,
        a, abbr, acronym, address, big, cite, code,
        del, dfn, em, img, ins, kbd, q, s, samp,
        small, strike, strong, sub, sup, tt, var,
        b, u, i, center,
        dl, dt, dd, ol, ul, li,
        fieldset, form, label, legend,
        table, caption, tbody, tfoot, thead, tr, th, td,
        article, aside, canvas, details, embed,
        figure, figcaption, footer, header, hgroup,
        menu, nav, output, ruby, section, summary,
        time, mark, audio, video {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 100%;
            font: inherit;
            vertical-align: baseline;
        }
        /* HTML5 display-role reset for older browsers */
        article, aside, details, figcaption, figure,
        footer, header, hgroup, menu, nav, section {
            display: block;
        }
        body {
            line-height: 1;
        }
        ol, ul {
            list-style: none;
        }
        blockquote, q {
            quotes: none;
        }
        blockquote:before, blockquote:after,
        q:before, q:after {
            content: '';
            content: none;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
        }
        /*! normalize.css v8.0.1 | MIT License | github.com/necolas/normalize.css */

        /* Document
		========================================================================== */

        /**
		* 1. Correct the line height in all browsers.
		* 2. Prevent adjustments of font size after orientation changes in iOS.
		*/

        html {
            line-height: 1.15; /* 1 */
            -webkit-text-size-adjust: 100%; /* 2 */
        }

        /* Sections
		========================================================================== */

        /**
		* Remove the margin in all browsers.
		*/

        body {
            margin: 0;
        }

        /**
		* Render the `main` element consistently in IE.
		*/

        main {
            display: block;
        }

        /**
		* Correct the font size and margin on `h1` elements within `section` and
		* `article` contexts in Chrome, Firefox, and Safari.
		*/

        h1 {
            font-size: 2em;
            margin: 0.67em 0;
        }

        /* Grouping content
		========================================================================== */

        /**
		* 1. Add the correct box sizing in Firefox.
		* 2. Show the overflow in Edge and IE.
		*/

        hr {
            box-sizing: content-box; /* 1 */
            height: 0; /* 1 */
            overflow: visible; /* 2 */
        }

        /**
		* 1. Correct the inheritance and scaling of font size in all browsers.
		* 2. Correct the odd `em` font sizing in all browsers.
		*/

        pre {
            font-family: monospace, monospace; /* 1 */
            font-size: 1em; /* 2 */
        }

        /* Text-level semantics
		========================================================================== */

        /**
		* Remove the gray background on active links in IE 10.
		*/

        a {
            background-color: transparent;
        }

        /**
		* 1. Remove the bottom border in Chrome 57-
		* 2. Add the correct text decoration in Chrome, Edge, IE, Opera, and Safari.
		*/

        abbr[title] {
            border-bottom: none; /* 1 */
            text-decoration: underline; /* 2 */
            text-decoration: underline dotted; /* 2 */
        }

        /**
		* Add the correct font weight in Chrome, Edge, and Safari.
		*/

        b,
        strong {
            font-weight: bolder;
        }

        /**
		* 1. Correct the inheritance and scaling of font size in all browsers.
		* 2. Correct the odd `em` font sizing in all browsers.
		*/

        code,
        kbd,
        samp {
            font-family: monospace, monospace; /* 1 */
            font-size: 1em; /* 2 */
        }

        /**
		* Add the correct font size in all browsers.
		*/

        small {
            font-size: 80%;
        }

        /**
		* Prevent `sub` and `sup` elements from affecting the line height in
		* all browsers.
		*/

        sub,
        sup {
            font-size: 75%;
            line-height: 0;
            position: relative;
            vertical-align: baseline;
        }

        sub {
            bottom: -0.25em;
        }

        sup {
            top: -0.5em;
        }

        /* Embedded content
		========================================================================== */

        /**
		* Remove the border on images inside links in IE 10.
		*/

        img {
            border-style: none;
        }

        /* Forms
		========================================================================== */

        /**
		* 1. Change the font styles in all browsers.
		* 2. Remove the margin in Firefox and Safari.
		*/

        button,
        input,
        optgroup,
        select,
        textarea {
            font-family: inherit; /* 1 */
            font-size: 100%; /* 1 */
            line-height: 1.15; /* 1 */
            margin: 0; /* 2 */
        }

        /**
		* Show the overflow in IE.
		* 1. Show the overflow in Edge.
		*/

        button,
        input { /* 1 */
            overflow: visible;
        }

        /**
		* Remove the inheritance of text transform in Edge, Firefox, and IE.
		* 1. Remove the inheritance of text transform in Firefox.
		*/

        button,
        select { /* 1 */
            text-transform: none;
        }

        /**
		* Correct the inability to style clickable types in iOS and Safari.
		*/

        button,
        [type="button"],
        [type="reset"],
        [type="submit"] {
            -webkit-appearance: button;
        }

        /**
		* Remove the inner border and padding in Firefox.
		*/

        button::-moz-focus-inner,
        [type="button"]::-moz-focus-inner,
        [type="reset"]::-moz-focus-inner,
        [type="submit"]::-moz-focus-inner {
            border-style: none;
            padding: 0;
        }

        /**
		* Restore the focus styles unset by the previous rule.
		*/

        button:-moz-focusring,
        [type="button"]:-moz-focusring,
        [type="reset"]:-moz-focusring,
        [type="submit"]:-moz-focusring {
            outline: 1px dotted ButtonText;
        }

        /**
		* Correct the padding in Firefox.
		*/

        fieldset {
            padding: 0.35em 0.75em 0.625em;
        }

        /**
		* 1. Correct the text wrapping in Edge and IE.
		* 2. Correct the color inheritance from `fieldset` elements in IE.
		* 3. Remove the padding so developers are not caught out when they zero out
		*    `fieldset` elements in all browsers.
		*/

        legend {
            box-sizing: border-box; /* 1 */
            color: inherit; /* 2 */
            display: table; /* 1 */
            max-width: 100%; /* 1 */
            padding: 0; /* 3 */
            white-space: normal; /* 1 */
        }

        /**
		* Add the correct vertical alignment in Chrome, Firefox, and Opera.
		*/

        progress {
            vertical-align: baseline;
        }

        /**
		* Remove the default vertical scrollbar in IE 10+.
		*/

        textarea {
            overflow: auto;
        }

        /**
		* 1. Add the correct box sizing in IE 10.
		* 2. Remove the padding in IE 10.
		*/

        [type="checkbox"],
        [type="radio"] {
            box-sizing: border-box; /* 1 */
            padding: 0; /* 2 */
        }

        /**
		* Correct the cursor style of increment and decrement buttons in Chrome.
		*/

        [type="number"]::-webkit-inner-spin-button,
        [type="number"]::-webkit-outer-spin-button {
            height: auto;
        }

        /**
		* 1. Correct the odd appearance in Chrome and Safari.
		* 2. Correct the outline style in Safari.
		*/

        [type="search"] {
            -webkit-appearance: textfield; /* 1 */
            outline-offset: -2px; /* 2 */
        }

        /**
		* Remove the inner padding in Chrome and Safari on macOS.
		*/

        [type="search"]::-webkit-search-decoration {
            -webkit-appearance: none;
        }

        /**
		* 1. Correct the inability to style clickable types in iOS and Safari.
		* 2. Change font properties to `inherit` in Safari.
		*/

        ::-webkit-file-upload-button {
            -webkit-appearance: button; /* 1 */
            font: inherit; /* 2 */
        }

        /* Interactive
		========================================================================== */

        /*
		* Add the correct display in Edge, IE 10+, and Firefox.
		*/

        details {
            display: block;
        }

        /*
		* Add the correct display in all browsers.
		*/

        summary {
            display: list-item;
        }

        /* Misc
		========================================================================== */

        /**
		* Add the correct display in IE 10+.
		*/

        template {
            display: none;
        }

        [hidden] {
            display: none;
        }
	</style>
	<style>
        .main-box{
            width: 100%;
        }
        .main-box__header-box{
            display: flex;
            flex-direction: row;
            width: 100%;
            position: sticky;
            top: 0;
        }
        .main-box__rows-box{
            display: flex;
            flex-direction: column;
            width: 100%;
        }
        .el-komp{
            background-color: #1e90ff;
            color: black;
            font-weight: bold;
        }
        .el-deal{
            background-color: #8fbc8f;
            color: black;
            font-weight: bold;
        }
        .el-priznakDeal{
            background-color: #f8cbad;
            color: black;
            font-weight: bold;
        }
        .main-box__header-box__element{
            border: 1px solid black;
            border-right: 0px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-width: 150px;
            text-align: center;
        }
        .main-box__header-box__element:last-child{
            border: 1px solid black;
        }
        .main-box__rows-box__element{
            display: flex;
            flex-direction: row;
        }
        .main-box__rows-box__element-field{
            border: 1px solid black;
            border-right: 0px;
            border-top: 0px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            min-width: 150px;
            text-align: center;
            flex-direction: column;
            overflow: hidden;
        }
        .main-box__rows-box__element-field:last-child{
            border: 1px solid black;
            border-top: 0px;
        }
        .main-box__rows-box__element-field .element-field-mnog{
            border-bottom: 1px solid black;
            width: 100%;
            height: 35px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            overflow: auto
        }
        .main-box__rows-box__element-field .element-field-mnog:last-child{
            border-bottom: 0px;
        }
        .hidden{
            opacity: 0;
        }
        .hidden-none{
            display: none
        }
        .modal-filter{
            position: absolute;
            width: 1300px;
            height: 500px;
            background-color: white;
            top: 20px;
            left: 20px;
            border-radius: 20px;
            border: 1px solid gray;
            padding: 20px;
            z-index: 10;
        }
        .close-button{
            width: 20px;
            height: 5px;
            background-color: black;
            position: absolute;
            top: 20px;
            right: 10px;
            transform: rotate(45deg);
            border: none;
            cursor: pointer;
        }
        .close-button::before{
            content:'';
            display: block;
            width: 20px;
            height: 5px;
            background-color: black;
            position: absolute;
            top: 0px;
            right: 0px;
            transform: rotate(90deg);
        }
        .filter{
            display: flex;
            flex-direction: column;
            width: 210px;
            margin-bottom: 10px;
        }
        .filter span{
            margin-bottom: 10px;
            color: #a9adb2;
            font-size: 13px;
        }
        input, option{
            cursor: pointer;
        }
        .button-spec{
            margin: 20px 20px 20px 10px;
        }
        .spec-div1{
            display: flex;
            flex-direction: row;
            gap: 10px;
        }
        .filter-spec{
            align-items: flex-start;
        }
        .el-komp-spec-1{
            min-width: 110px;
        }
        .el-komp-spec-2{
            min-width: 95px;
        }
        .elem-spec-1{
            padding: 5px 0px;
        }
        .elem-spec-2{
            padding: 5px 0px;
            height: 35px;
        }
        .silka-spec{
            color: black;
            cursor: pointer;
            text-decoration: none;
        }
	</style>
	<style>
        table#myTableOtchet,
        table#myTableOtchet th,
        table#myTableOtchet td{
            border: 1px solid #ddd;
            text-align: center;
            vertical-align: middle;
            min-width: 150px;
            padding: 10px;
            min-height: 35px;
        }
        #myTableOtchet {
            border-collapse: collapse;
            width: 100%;
        }

        #myTableOtchet th {
            position: sticky;
            top: 0;
            /*background-color: yellow; /* Цвет фона заголовка */
            z-index: 2; /* Обеспечиваем, чтобы заголовок был выше других элементов */
        }

        table#myTableOtchet td.deals {
            padding: 0;
        }

        /*#myTableOtchet th, #myTableOtchet td {
			padding: 10px;
			border: 1px solid #ddd;
		}*/

        /*#myTableOtchet tbody {
			display: block;
			height: 100vh;
			overflow-y: auto;
		}*/

        /*#myTableOtchet thead, #myTableOtchet tbody tr {
			display: table;
			width: 100%;
			table-layout: fixed;
		}*/
	</style>
</head>
<body>
<div>

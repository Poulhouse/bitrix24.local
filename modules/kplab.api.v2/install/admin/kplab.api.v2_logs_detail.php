<?
use Bitrix\Main\Application;
use Bitrix\Main\Page\Asset;

require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php';

// Подключение в меню
$APPLICATION->SetTitle("Детали запроса");
Asset::getInstance()->addCss("/bitrix/css/main/bootstrap.css");
$aContext = array(
    array(
        "TEXT" => "Вернуться к списку",
        "LINK" => "kplab.api.v2_logs_list.php",
        "TITLE" => "Вернуться к списку",
        "ICON" => "btn_list",
    ),
);
$context = new CAdminContextMenu($aContext);
$context->Show();

$connection = Application::getConnection();
if (isset($_GET['ID'])) {
    $id = intval($_GET['ID']);
    $sql = "SELECT * FROM kplab_api_logs WHERE id = $id";
    $res = $connection->query($sql);

    ?>
    <style>
        .detail-request {
            padding: 0;
            border-radius: 8px;
            box-shadow: -4px 0px 4px 0px rgba(0, 0, 0, 0.25);
            background: rgb(255, 255, 255);
        }
        .header-container {
            padding: 15px 30px 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-left {
            display: flex;
            align-items: flex-start;
            flex-direction: column;
            justify-content: center;
        }
        .muted {
            color: rgb(102, 102, 102);
            font-family: Roboto;
            font-size: 12px;
            font-weight: 400;
            line-height: 14px;
            letter-spacing: 0px;
        }
        .header-left span{
            color: rgb(102, 102, 102);
            font-family: Roboto;
            font-size: 12px;
            font-weight: 400;
            line-height: 14px;
            letter-spacing: 0px;
            text-align: left;
            margin: 0;
            padding: 0;
            padding-bottom: 7px;
        }
        .header-left h2 {
            color: rgb(51, 51, 51);
            font-family: Roboto;
            font-size: 16px;
            font-weight: 400;
            line-height: 19px;
            letter-spacing: 0px;
            text-align: left;
            margin: 0;
            padding: 0;
        }
        .header-right {
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            flex-direction: column;
        }
        .header-right .strong {
            color: rgb(51, 51, 51);
            font-family: Roboto;
            font-size: 12px;
            font-weight: 400;
            line-height: 14px;
            letter-spacing: 0px;
            text-align: right;
        }
        .main-container {
            display: flex;
            flex-direction: row;
            padding: 0;
            border-top: 1px solid #ddd;
        }
        .request-info {
            width: 40%;
            padding: 30px;
            border-right: 1px solid #ddd;
            display: flex;
            flex-direction: column;
        }
        .headers-info {
            display: flex;
            flex-direction: column;
        }
        .headers-info div .strong {
            max-width: 100%;
            width: 350px;
            word-break: break-all;
        }
        .headers-info div, .request-info div {
            margin-bottom: 10px;
            display: inline-flex;
            justify-content: flex-start;
            flex-direction: row;
            flex-wrap: wrap;
            align-items: flex-start;
        }
        .headers-info div .muted, .request-info div .muted {
            min-width: 150px;
        }
        .request-info div .strong {
            max-width: 100%;
            word-break: break-all;
        }
        .main-container .content {
            width: 70%;
            display: flex;
            flex-direction: column;
        }
        .content-section {
            display: flex;
            padding: 30px;
            position: relative;
            border-bottom: 1px solid #ddd;
            gap: 25px;
            flex-direction: column;
        }
        .content-section > span.strong {
            color: rgb(102, 102, 102);
            font-family: Roboto;
            font-size: 14px;
            font-weight: 700;
            line-height: 14px;
            letter-spacing: 0px;
            text-align: left;
        }

        .code-block {
            --tw-border-opacity: 1;
            border-color: rgb(217 217 217 / var(--tw-border-opacity));
            position: relative;
            width: 100%;
            border-radius: .75rem;
            border-style: solid;
            border-width: 1px;
        }
        .code-block_head {
            border-top-left-radius: .75rem;
            border-top-right-radius: .75rem;
            --tw-bg-opacity: 1;
            background-color: rgb(244 245 246 / var(--tw-bg-opacity));
        }
        .code-block_head-wrap {
            display: flex;
            align-items: center;
            padding: 8px 16px;
        }
        .code-block_head-wrap span {
            font-size: 12px;
            line-height: 16px;
            letter-spacing: normal;
            font-weight: 700;
        }
        .code-block_pre {
            display: flex;
            overflow-y: auto;
            padding: 12px 16px;
            margin: 0;
        }

    </style>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/default.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/languages/json.min.js"></script>
    <script>hljs.highlightAll();</script>

    <div class="detail-request container mt-4">
        <? if ($row = $res->fetch()) {
            $timestamp = MakeTimeStamp(htmlspecialcharsbx($row['request_time']));
            $formattedDate = CIBlockFormatProperties::DateFormat("j F Y H:i:s", $timestamp);
            $headers = json_decode($row['request_headers'], true);
            $headersArray = [];

            foreach ($headers as $key => $value) {
                //list($key, $value) = explode(': ', $header, 2);
                $headersArray[$key] = $value;
            }
            ?>
            <!-- Header Section -->
            <header>
                <div class="header-container">
                    <div class="header-left">
                        <span class="muted"><?= $formattedDate; ?></span>
                        <h2><?= htmlspecialcharsbx($row['request_type']); ?> запрос <b><?= htmlspecialcharsbx($row['title']); ?> (<?= htmlspecialcharsbx($row['partner_name']); ?>)</b></h2>
                    </div>
                    <div class="header-right">
                        <span class="muted">ID запроса:</span>
                        <span class="strong"><?= htmlspecialcharsbx($row['id']); ?></span>
                    </div>
                </div>
            </header>

            <!-- Main Content Section -->
            <main>
                <div class="main-container">
                    <!-- Request Information Section -->
                    <div class="request-info">
                        <div>
                            <span class="muted">Время ответа:</span>
                            <span class="strong"><?= htmlspecialcharsbx($row['execution_time']); ?> сек.</span>
                        </div>
                        <div>
                            <span class="muted">HTTP метод:</span>
                            <span class="strong"><?= htmlspecialcharsbx($row['request_method']); ?></span>
                        </div>
                        <div>
                            <span class="muted">Статус запроса:</span>
                            <? if ( htmlspecialcharsbx($row['request_status']) == "Success") { ?>
                                <span class="strong" style="color: green; font-weight: 700;"><?= htmlspecialcharsbx($row['request_status']); ?></span>
                            <? } else { ?>
                                <span class="strong" style="color: red; font-weight: 700;"><?= htmlspecialcharsbx($row['request_status']); ?></span>
                            <? } ?>
                        </div>
                        <div>
                            <span class="muted">URL запроса:</span>
                            <span class="strong"><?= htmlspecialcharsbx($row['request_url']); ?></span>
                        </div>
                        <div>
                            <span class="muted">API метод:</span>
                            <span class="strong"><?= htmlspecialcharsbx($row['method_name']); ?></span>
                        </div>
                        <div>
                            <span class="muted">API Контроллер:</span>
                            <span class="strong"><?= htmlspecialcharsbx($row['controller_name']); ?></span>
                        </div>
                        <div>
                            <span class="muted">Объект интеграции:</span>
                            <span class="strong"><?= htmlspecialcharsbx($row['object_url']); ?></span>
                        </div>
                    </div>

                    <!-- Main Dashboard Content -->
                    <div class="content">
                        <div class="content-section">
                            <span class="strong">Заголовки запроса:</span>

                            <? if (!empty($headersArray)) { ?>

                                <div class="headers-info">

                                    <?foreach ($headersArray as $key => $value) {?>

                                        <div>
                                            <span class="muted"><?= $key ?>:</span>
                                            <span class="strong"><?= $value ?></span>
                                        </div>

                                    <? } ?>

                                </div>

                            <? } ?>

                        </div>

                        <div class="content-section">
                            <span class="strong">Тело запроса:</span>

                            <div class="code-block">
                                <div class="code-block_head">
                                    <div class="code-block_head-wrap">
                                        <span class="caption-xs">json</span>
                                    </div>
                                </div>

                                <?php
                                // Проверяем, является ли JSON корректным, и исправляем формат при необходимости
                                $request_body_json = json_encode(json_decode(strip_tags(htmlspecialchars_decode($row['request_body'])),true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                $request_body_json = ltrim($request_body_json);
                                ?>
                                <pre id="request_body_json" class="code-block_pre" tabindex="0">
                                    <code class="json">
                                        <?= htmlspecialchars($request_body_json); ?>
                                    </code>
                                </pre>

                            </div>

                        </div>

                        <div class="content-section">
                            <span class="strong">Ответ на запрос:</span>

                            <div class="code-block">
                                <div class="code-block_head">
                                    <div class="code-block_head-wrap">
                                        <span class="caption-xs">json</span>
                                    </div>
                                </div>
                                <?php
                                // Проверяем, является ли JSON корректным, и исправляем формат при необходимости
                                //$response_json = strip_tags(htmlspecialchars_decode($row['response']));
                                $resultDecoded = json_decode($row['response'], true);
                                $resultToSave = $resultDecoded !== null ? json_encode($resultDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '"' . $row['response']. '"';
                                $resultToSave = ltrim($resultToSave);
                                ?>
                                <pre id="response_json" class="code-block_pre" tabindex="0">
                                    <code class="json">
                                        <?= htmlspecialchars($resultToSave); ?>
                                    </code>
                                </pre>
                            </div>

                        </div>
                    </div>
                </div>
            </main>

        <? } else { ?>

            <header>
                <div class="header-container">
                    <div class="header-left">
                        <span class="muted">01 Января 1997 00:00:00</span>
                        <h2>Нет запроса</h2>
                    </div>
                    <div class="header-right">
                        <span class="muted">ID запроса:</span>
                        <span class="strong">null</span>
                    </div>
                </div>
            </header>
            <!-- Main Content Section -->
            <main>
                <div class="main-container">
                </div>
            </main>

        <? } ?>

    </div>
    <?php
}

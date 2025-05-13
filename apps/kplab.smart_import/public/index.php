<?php

declare(strict_types=1);
ini_set('display_errors', 'On');
use Bitrix24\SDK\Core\Credentials\ApplicationProfile;
use Bitrix24\SDK\Services\ServiceBuilderFactory;
use Symfony\Component\HttpFoundation\Request;

require_once '../vendor/autoload.php';

$appProfile = ApplicationProfile::initFromArray([
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_ID' => 'local.67c861b2551452.09112784',
    'BITRIX24_PHP_SDK_APPLICATION_CLIENT_SECRET' => '3wZxvFL5faHPSVSVh7MYz8tXsjMPp92514Z4jyZ2JY3si5NEU5',
    'BITRIX24_PHP_SDK_APPLICATION_SCOPE' => 'crm,user_basic,placement'
]);

$B24 = ServiceBuilderFactory::createServiceBuilderFromPlacementRequest(
    Request::createFromGlobals(),
    $appProfile
);

$host = $_SERVER['HTTP_HOST'];
$handlerDir = '/local/apps/kplab.smart_import/src/';


$currentPlacements = $B24->core->call('placement.get',[])->getResponseData()->getResult();
$smartProcesses = $B24->core->call('crm.type.list',[])->getResponseData()->getResult()["types"];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        .smart {
            display: flex;
            flex-direction: row;
            gap: 16px;
            margin-bottom: 16px;
            align-items: center;
        }
        .smart p {
            margin: 0;
        }
        button.btn {
            padding: 8px 16px;
            border: 0;
            border-radius: 8px;
            cursor:pointer;
        }
        button.btn.btn-success {
            background-color: #0bae3e;
            color: #ffffff;
        }
        button.btn.btn-danger {
            background-color: #ff0000;
            color: #ffffff;
        }
    </style>
    <script
            src="https://code.jquery.com/jquery-3.6.0.js"
            integrity="sha256-H+K7U5CnXl1h5ywQfKtSj8PCmoN9aaq30gDh27Xc0jk="
            crossorigin="anonymous"></script>

    <script src="//api.bitrix24.com/api/v1/"></script>
    <title>B24PhpSDK local-app demo</title>
</head>

<body class="container-fluid">
<div class="row">
    <div class="col-md-12 col-lg-12 text-small">
        <h2>Встройка импорта в смарт-процесс</h2>
        <p>Application is working with auth tokens from Bitrix24:</p>
        <pre>
            <?php
            print_r($_REQUEST);
            ?>
        </pre>
    </div>
</div>
<div class="row">
    <div class="col-md-12 col-lg-12">

        <?php
        foreach ($smartProcesses as $process):
            $isDelete = false;
            $arPlacement = [
                'PLACEMENT' => 'CRM_DYNAMIC_'.$process["entityTypeId"].'_LIST_TOOLBAR',
                'TITLE' => 'Импорт в смарт-процесс',
                'HANDLER' => 'https://' . $host . $handlerDir . 'handler.php',
            ];
            foreach ($currentPlacements as $placement):
                if($placement['placement'] == $arPlacement['PLACEMENT'])
                    $isDelete = true;
            endforeach;
            ?>
            <div class="smart">
                <p>Приложение появится в CRM - <?=$process["title"]?></p>
                <?php
                if($isDelete):
                    ?>

                    <button class="btn btn-danger" onclick="
                            BX24.callMethod('placement.unbind', {
                            PLACEMENT: '<?=$arPlacement['PLACEMENT']?>',
                            HANDLER: '<?=$arPlacement['HANDLER']?>'
                            })">
                        Удалить
                    </button>

                <?php
                endif;
                if(!$isDelete):
                    ?>
                    <button  class="btn btn-success" onclick="
                            BX24.callMethod('placement.bind', {
                            PLACEMENT: '<?=$arPlacement['PLACEMENT']?>',
                            HANDLER: '<?=$arPlacement['HANDLER']?>',
                            TITLE: '<?=$arPlacement['TITLE']?>'
                            })">
                        Установить
                    </button>

                <?php
                endif;
                ?>
            </div>
        <?php
        endforeach;
        ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        BX24.init(function () {
            console.log('bx24.js initialized', BX24.isAdmin());
        });
    });
</script>
</body>
</html>


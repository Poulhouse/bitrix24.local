<?php
// public/install.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Installer.php';

use Kplab\ExchangeLog\Installer;

$installer = new Installer();

if ($installer->install()) {
    echo "Приложение для отслеживания изменений успешно установлено.";
} else {
    echo "Ошибка при установке приложения.";
}
?>
<script src="//api.bitrix24.com/api/v1/"></script>
<script>
    BX24.init(function(){
        BX24.installFinish();
    });
</script>

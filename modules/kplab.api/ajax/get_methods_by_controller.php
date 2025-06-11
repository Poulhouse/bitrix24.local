<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Loader;
use KPLab\API\V2\Model\ORM\RoutesTable;

header('Content-Type: application/json');

if (!Loader::includeModule('kplab.api')) {
    echo json_encode(['error' => 'module not loaded']);
    return;
}

$controller = $_REQUEST['controller'] ?? '';

if (!$controller) {
    echo json_encode([]);
    return;
}

$methods = [];

$result = RoutesTable::getList([
    'select' => ['METHOD_NAME'],
    'filter' => ['=CONTROLLER_NAME' => $controller],
    'group' => ['METHOD_NAME'],
    'order' => ['METHOD_NAME' => 'ASC'],
]);

while ($row = $result->fetch()) {
    if (!empty($row['METHOD_NAME'])) {
        $methods[] = $row['METHOD_NAME'];
    }
}

echo json_encode($methods);

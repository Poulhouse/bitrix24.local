<?php

namespace Whatasoft\Statistic;

use Bitrix\Highloadblock\HighloadBlockTable;
use Whatasoft\Helpers\Traits\ModuleLoader;

class CallReportStorage {
  
  use ModuleLoader;
  
  private $requiredModules = ['highloadblock'];
  private $storage;
  
  // ID Highload-блока с отчётами
  const HLBLOCK_ID = 5;
  
  public function __construct()
  {
    $this->includeModules();
    $this->storage = $this->getStorage();
  }
  
  public function getStorage()
  {
    $arFilter = ['ID' => self::HLBLOCK_ID];
    $arParams = ['filter' => $arFilter];
    $block = HighloadBlockTable::getList($arParams)->fetch();
    $entity = HighloadBlockTable::compileEntity($block);
    return $entity->getDataClass();
  }
  
  public function saveReport(array $report)
  {
    $arFields = [
      'UF_DEAL_ID' => $report['DEAL_ID'],
      'UF_CONTACT_ID' => $report['CONTACT_ID'],
      'UF_REPORT_TYPE_CODE' => $report['REPORT_TYPE_CODE'],
      'UF_QUEUE_ID' => $report['QUEUE_ID'],
      'UF_MANAGER_ID' => $report['MANAGER_ID'],
      'UF_STAGE_ID' => $report['STAGE_ID'],
      'UF_MANAGER_FULL_NAME' => $report['MANAGER_NAME'],
      'UF_CALL_DATETIME' => new \Bitrix\Main\Type\DateTime($report['CALL_DATETIME'], CallReport::REPORT_DATETIME_FORMAT),
      'UF_JSON_LOG' => json_encode($report),
      'UF_MANAGER_FULL_NAME' => $report['UF_REPORT_COMPANY_NAME'] ?? '',

    ];

    $success = $this->storage::add($arFields);
    return $success->isSuccess() ? $success->getId() : false;
  }
  
  public function getReportFromRow(array $row)
  {
    $json = $row['UF_JSON_LOG'] ?? null;
    $report = ($json) ? json_decode($json, true) : [];
    $report['ID'] = $row['ID'];
    return $report;
  }
  
  public function getReportsFromRows(array $rows)
  {
    return array_values(array_map(function($row) {
      return $this->getReportFromRow($row);
    }, $rows));
  }
  
  public function getReportById($id)
  {
    $row = $this->getById($id, []);
    return $this->getReportFromRow($row);
  }
  
  public function getById($id, $default = null)
  {
    $arFilter = ['ID' => $id];
    $arResult = $this->getFiltered($arFilter)->fetch();
    return is_array($arResult) ? $arResult : $default;
  }
  
  public function getFiltered($arFilter = [], $arOrder = [], $arSelect = ['*'], $arCustomParams = [])
  {
    $arParams = [
      'select' => $arSelect,
      'filter' => $arFilter,
      'order' => $arOrder,
      'group'   => ['UF_CALL_DATETIME']
    ];
    
    $arParams = array_merge($arParams, $arCustomParams);
    
    return $this->storage::getList($arParams);
  }
  
  public function getCount($arFilter = [])
  {
    return (int)$this->storage::getCount($arFilter);
  }
}
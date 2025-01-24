<?php

namespace Whatasoft\Statistic;

use Whatasoft\Statistic\Helpers\CrmFieldManager;

class CallReportCsvExport {
  
  private $crmFieldManager = null;
  private $filePath;
  private $reports = [];
  
  const CSV_DELIMITER = ';';
  
  public function __construct(array $reports)
  {
    $this->reports = $reports;
    $this->crmFieldManager = new CrmFieldManager();
    $this->filePath = $this->getFilepath();
  }
  
  public function getFilepath()
  {
    return $_SERVER['DOCUMENT_ROOT'] . '/upload/csv_reports/' . time() . '.csv';
  }
  
  public function getReportFile()
  {
    $handler = fopen($this->filePath, 'w+');
    $this->makeReport($handler);
    return $this->filePath;
  }
  
  public function downloadReport($filename = 'report.csv')
  {
    global $APPLICATION;
    $APPLICATION->RestartBuffer();
    header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=".$filename);
		header("Pragma: no-cache");
		header("Expires: 0");
		$handler = fopen('php://output', 'w');
		$this->makeReport($handler);
    die();
  }
  
  public function convertValuesToWindows1251(array $values)
  {
    return array_map(function($item){
      return $this->convertString($item, 'windows-1251');
    }, $values);
  }
  
  public function convertString($string, $toEncoding, $fromEncoding = 'UTF-8')
  {
    return mb_convert_encoding($string, $toEncoding, $fromEncoding);
  }
  
  public function getReportCsvHeader()
  {
    return array_merge(
      $this->getMainCsvHeaderFields(),
      $this->getContactCsvHeaderFields(),
      $this->getDealCsvHeaderFields()
    );
  }

  private function makeReport($handler)
  {
    $csvHeader = array_values($this->getReportCsvHeader());
    $convertedCsvHeader = $this->convertValuesToWindows1251($csvHeader);
    
    fputcsv($handler, $convertedCsvHeader, self::CSV_DELIMITER);
    
    foreach($this->reports as $arReport) {
      
      $csvFields = [];
      $mainFields = array_keys($this->getMainCsvHeaderFields());
      $contactFields = array_keys($this->getContactCsvHeaderFields());
      $dealFields = array_keys($this->getDealCsvHeaderFields());
      
      foreach($mainFields as $fieldCode) {
        $csvFields[] = $arReport[$fieldCode] ?? '';
      }
      
      foreach($contactFields as $fieldCode) {
        $arReportField = $arReport['CONTACT_FIELDS'][$fieldCode] ?? [];
        $csvFields[] = $arReportField['VALUE'] ?? '';
      }
      
      foreach($dealFields as $fieldCode) {
        $arReportField = $arReport['DEAL_FIELDS'][$fieldCode] ?? [];
        $csvFields[] = $arReportField['VALUE'] ?? '';
      }
      
      $convertedCsvFields = $this->convertValuesToWindows1251($csvFields);
      fputcsv($handler, $convertedCsvFields, self::CSV_DELIMITER);
    }
    
    fclose($handler);
  }
  
  private function getMainCsvHeaderFields()
  {
    return [
      'MANAGER_NAME' => 'ФИО Менеджера',
      'DEAL_ID' => 'ID сделки',
      'CONTACT_ID' => 'ID контакта',
      'STATUS_NAME' => 'Статус сделки',
      'QUEUE_NAME' => 'Название очереди',
      'CALL_DATETIME' => 'Дата/время звонка',
      'CLIENT_LAST_NAME' => 'Фамилия',
      'CLIENT_NAME' => 'Имя',
      'CLIENT_SECOND_NAME' => 'Отчество',
      'PHONE_TO_CALL' => 'Телефон',
      'CALL_STATUS' => 'Статус',
    ];
  }
  
  private function getContactCsvHeaderFields()
  {
    return $this->crmFieldManager->getContactAllFieldsNames(CallReport::EXCLUDED_CONTACT_FIELDS);
  }
  
  private function getDealCsvHeaderFields()
  {
    return $this->crmFieldManager->getDealAllFieldsNames(CallReport::EXCLUDED_DEAL_FIELDS);
  }
}
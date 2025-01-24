<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Whatasoft\Statistic\CallReport;
use Whatasoft\Statistic\CallReportStorage;
use Whatasoft\Statistic\CallReportFilter;
use Whatasoft\Statistic\CallReportCsvExport;
use Whatasoft\Statistic\Helpers\CrmFieldManager;


class StatisticReportsComponent extends CBitrixComponent
{
  const GRID_ID = 'consolidated_report';

  private $filter;
  private $storage;
  private $bxGridOptions;
  private $nav;
  private $bxFilterOptions;
  private $bxFilterData = [];
    
  public function onPrepareComponentParams($arParams)
  {
    $arParams['REPORT_TYPE_CODE'] = $arParams['REPORT_TYPE_CODE'] ?? false;
    return $arParams;
  }

  public function executeComponent()
  {
    $this->storage = new CallReportStorage();
    $this->filter = new CallReportFilter();
    $this->bxGridOptions = new Bitrix\Main\Grid\Options(self::GRID_ID);
    $this->nav = new Bitrix\Main\UI\PageNavigation(self::GRID_ID);
    $this->bxFilterOptions = new Bitrix\Main\UI\Filter\Options(self::GRID_ID);
    $this->bxFilterData = $this->bxFilterOptions->getFilter([]);
    $this->crmFieldManager = new CrmFieldManager();
    $this->arResult = [];
    $this->arResult['GRID_ID'] = self::GRID_ID;
    
    $this->collectBitrixFilter();
    $this->processRequest();
    $this->countResultRows();
    $this->setBitrixFilterList();
    $this->setNavObject();
    $this->setBitrixTableHeader();
    $this->obtainReports($this->filter->getFilter());
    $this->setTableRows();
    $this->includeComponentTemplate();
  }
  
  private function collectBitrixFilter()
  {
    $filter = $this->bxFilterData;
    
    if (!empty($filter['DATE_from'])) {
      $this->filter->setCallDateTimeFrom($filter['DATE_from']);
    }
  
    if (!empty($filter['DATE_to'])) {
      $this->filter->setCallDateTimeTo($filter['DATE_to']);
    }
    
    if (!empty($filter['ID_from'])) {
      $this->filter->setIdFrom($filter['ID_from']);
    }
  
    if (!empty($filter['ID_to'])) {
      $this->filter->setIdTo($filter['ID_to']);
    }
    
    if (!empty($filter['QUEUE']) && is_array($filter['QUEUE'])) {
      $this->filter->setQueues($filter['QUEUE']);
    }
    
    if (!empty($filter['STAGE']) && is_array($filter['STAGE'])) {
      $this->filter->setStages($filter['STAGE']);
    }
    
    if (!empty($filter['MANAGER_NAME']) && strlen(trim($filter['MANAGER_NAME']))) {
      $this->filter->setManagerName($filter['MANAGER_NAME']);
    }
    
    if ($this->arParams['REPORT_TYPE_CODE']) {
      $this->filter->setReportType(intval($this->arParams['REPORT_TYPE_CODE']));
    }
    
    $this->arResult['FILTER'] = $this->filter->getFilter();
    
    return $this;
  }
  
  private function processRequest()
  {
    if (isset($_REQUEST['GET_REPORT'])) {
      $this->obtainReports($this->filter->getFilter(), false);
      $reports = $this->arResult['REPORTS'];
      $csvReport = new CallReportCsvExport($reports);
      $csvReport->downloadReport();
    }
    
    if (isset($_REQUEST['GET_FULL_REPORT'])) {
      $this->obtainReports([], false);
      $reports = $this->arResult['REPORTS'];
      $csvReport = new CallReportCsvExport($reports);
      $csvReport->downloadReport();
    }
  }
  
  private function countResultRows()
  {
    $arFilter = $this->filter->getFilter();
    $this->arResult['COUNT_ROWS'] = $this->storage->getCount($arFilter);
    
    return $this;
  }
  
  private function setNavObject()
  {
    $total = $this->arResult['COUNT_ROWS'];
    $navParams = $this->bxGridOptions->GetNavParams();
    $this->nav->allowAllRecords(true)//Показать все
    ->setRecordCount($total)
    ->setPageSize($navParams['nPageSize'])
    ->initFromUri();
    
    $this->arResult['NAV'] = $this->nav;
    
    return $this;
  }
  
  private function setBitrixFilterList()
  {
    $statusList = $this->crmFieldManager->getDealStatusList(['STATUS_ID' => 'ASC'], ['ENTITY_ID' => 'DEAL_STAGE']);
    $queueList = $this->crmFieldManager->getQueueList(['NAME' => 'ASC'], [], ['ID', 'NAME']);
    $queueItems = [];
    $stageItems = [];
    
    foreach($queueList as $queueId => $arQueue) {
      $queueItems[$queueId] = $arQueue['NAME'];
    }
    
    foreach($statusList as $statusId => $arStatus) {
      $stageItems[$arStatus['STATUS_ID']] = $arStatus['NAME'];
    }
    $cancelReasonList = [
		 'Консультация на будущее',
 'Уже не актуально',
 'Не оставляли заявку',
 'Не устраивают условия программы',
 'Клиент не подходит по условиям программы',
 'Нет офиса в городе клиента',
 'Требуется юридическая помощь',
 'Обналичивание',
 'Кредитная политика заимодавца',
 'Кредитная история заемщика',
 'Кредитная история поручителя',
 'Избыточная долговая нагрузка на заемщика',
 'Избыточная долговая нагрузка на поручителя',
 'Несоответствие между заявкой и информацией известной кредитору',
 'Другая причина'
	];
    $filterList = [
      [
        'id' => 'ID',
        'type' => 'number',
        'name' => 'ID',
        'default' => true,
      ],
      [
        "id" => "MANAGER_NAME",
        'type' => 'text',
        "name" => 'ФИО Менеджера',
        "default" => true
      ],
      [
        'id' => 'DATE',
        'type' => 'date',
        'name' => 'Дата/время звонка',
        'default' => true,
      ],
      [
        'id' => 'QUEUE',
        'type' => 'list',
        'name' => 'Очередь',
        'default' => true,
        'items' => $queueItems,
        'params' => ['multiple' => 'Y'],
      ],
      [
        'id' => 'STAGE',
        'type' => 'list',
        'name' => 'Стадия сделки',
        'default' => true,
        'items' => $stageItems,
        'params' => ['multiple' => 'Y'],
      ],
		/* [
        'id' => 'CANCEL_REASON',
        'type' => 'list',
        'name' => 'Причина отказа',
        'default' => true,
        'items' => $stageItems,
        'params' => ['multiple' => 'Y'],
],*/
    ];
    
    $this->arResult['FILTER_LIST'] = $filterList;
    
    return $this;
  }
  
  private function setBitrixTableHeader()
  {
    $tableHeader = [];
    $mainFields = $this->getMainHeaderFields();
    $contactFields = $this->getContactHeaderFields();
    $dealFields = $this->getDealHeaderFields();

    foreach($mainFields as $fieldCode => $fieldName) {
      $tableHeader[] = [
        'id' => $fieldCode,
        'name' => $fieldName,
        'sort' => $fieldCode,
        'default' => true,
      ];
    }
    
    foreach($contactFields as $fieldCode => $fieldName) {
      $tableHeader[] = [
        'id' => 'CONTACT_' . $fieldCode,
        'name' => $fieldName,
		  //  'sort' => 'CONTACT_' . $fieldCode,
        'default' => false,
      ];
    }

    foreach($dealFields as $fieldCode => $fieldName) {
      $tableHeader[] = [
        'id' => 'DEAL' . $fieldCode,
        'name' => $fieldName,
        'sort' => 'DEAL_' . $fieldCode,
        'default' => false,
      ];
    }
    $this->arResult['TABLE_HEADER'] = $tableHeader;
    
    return $this;
  }
  
  private function getMainHeaderFields()
  {
    return [
      'ID' => 'ID',
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
  
  private function getContactHeaderFields()
  {
	  //	print_r($this->crmFieldManager->getContactAllFieldsNames(CallReport::EXCLUDED_CONTACT_FIELDS));
    return $this->crmFieldManager->getContactAllFieldsNames(CallReport::EXCLUDED_CONTACT_FIELDS);
  }
  
  private function getDealHeaderFields()
  {
    return $this->crmFieldManager->getDealAllFieldsNames(CallReport::EXCLUDED_DEAL_FIELDS);
  }
  
  private function obtainReports($arFilter = [], $usePagination = true)
  {
	$arOrder = ['UF_CALL_DATETIME' => 'DESC'];
	if (isset($_GET['by'])){
		if ($_GET['by'] == 'ID')
			$arOrder = [$_GET['by'] => $_GET['order']];
		elseif ($_GET['by'] == 'MANAGER_NAME')
			$arOrder = ['UF_MANAGER_FULL_NAME' => $_GET['order']];
		else
			$arOrder = ['UF_' . $_GET['by'] => $_GET['order']];

	}

    $arSelect = ['*'];
    $arCustomParams = [];
    
    if ($usePagination) {
      $arNavParams = [
        'limit' => $this->nav->getLimit(),
        'offset' => $this->nav->getOffset(),
      ];
      $arCustomParams = array_merge($arCustomParams, $arNavParams);
    }
    
    $rows = $this->storage->getFiltered(
      $arFilter,
      $arOrder,
      $arSelect,
      $arCustomParams
    )->fetchAll() ?? [];

    $this->arResult['REPORTS'] = $this->storage->getReportsFromRows($rows);
	  //	print_r($this->storage->getReportsFromRows($rows));
    return $this;
  }
  
  private function setTableRows()
  {
    $tableRows = [];
    $mainFieldCodes = array_keys($this->getMainHeaderFields());
    $contactFieldCodes = array_keys($this->getContactHeaderFields());
    $dealFieldCodes = array_keys($this->getDealHeaderFields());
    foreach($this->arResult['REPORTS'] as $arReport) {

      $tableRow = [];
      $tableRow['data'] = [];
      $tableRow['actions'] = [];

      foreach($mainFieldCodes as $fieldCode) {
        $tableRow['data'][$fieldCode] = $arReport[$fieldCode] ?? '';
      }

      foreach($contactFieldCodes as $fieldCode) {

        $field = $arReport['CONTACT_FIELDS'][$fieldCode] ?? [];
        $tableRow['data']['CONTACT_'.$fieldCode] = $field['VALUE'] ?? '';
	  }

	$dealFieldCodes[] = 'DEAL_UF_CRM_1606122059';
		$product = '';
		$deal = CCrmDeal::GetList(Array(),["ID"=>$arReport['DEAL_ID']],['ID', 'UF_CRM_1573736333'])->GetNext();
		if (isset($deal['UF_CRM_1573736333']) && $deal['UF_CRM_1573736333']>0){
			$item = (CIBlockElement::GetByID($deal['UF_CRM_1573736333'])->fetch());
			$product = $item['NAME'] ?? '';
		}
		//		print_r($deal);die();
		//	UF_CRM_1610713435
		//		print_r($arReport['DEAL_FIELDS'] );die();
			$allDealCats = (\Bitrix\Crm\Category\DealCategory::getAll());
      foreach($dealFieldCodes as $fieldCode) {
		  //	  echo $fieldCode . '<br />';
		if ($fieldCode == 'UF_CRM_1615633559'){
	        $field = $arReport['DEAL_FIELDS']['UF_CRM_DECLINE'] ?? [];
    	    $tableRow['data']['DEALUF_CRM_DECLINE'] = $field['VALUE'] ?? '';
  			$field = $arReport['DEAL_FIELDS']['UF_CRM_1615633559'] ?? [];
    	    $tableRow['data']['UF_CRM_1615633559'] = $field['VALUE'] ?? '';
    	    $tableRow['data']['DEALUF_CRM_1615633559'] = $field['VALUE'] ?? '';
		} elseif ($fieldCode == 'UF_CRM_MEET_DATE') { 
  			$field = $arReport['DEAL_FIELDS']['UF_CRM_MEET_DATE'] ?? [];
    	    $tableRow['data']['UF_CRM_MEET_DATE'] = $field['VALUE'] ?? '';
    	    $tableRow['data']['DEALUF_CRM_MEET_DATE'] = $field['VALUE'] ?? '';
		} elseif($fieldCode == 'DEAL_UF_CRM_1606122059'){
	        $field = $arReport['DEAL_FIELDS']['UF_CRM_1606122059'] ?? [];
    	    $tableRow['data']['UF_CRM_1606122059'] = $field['VALUE'] ?? '';
    	    $tableRow['data']['DEALUF_CRM_1606122059'] = $field['VALUE'] ?? '';
		} 
		 elseif($fieldCode == 'UF_CRM_1610713435'){
	        $field = $arReport['DEAL_FIELDS']['UF_CRM_1610713435'] ?? [];
    	    $tableRow['data']['UF_CRM_1610713435'] = $field['VALUE'] ?? '';
    	    $tableRow['data']['DEALUF_CRM_1610713435'] = $field['VALUE'] ?? '';
		}elseif($fieldCode == 'UF_CRM_1606123073'){
	        $field = $arReport['DEAL_FIELDS']['UF_CRM_1606123073'] ?? [];
    	    $tableRow['data']['UF_CRM_1606123073'] = $field['VALUE'] ?? '';
    	    $tableRow['data']['DEALUF_CRM_1606123073'] = $field['VALUE'] ?? '';
		}elseif($fieldCode == 'UF_CRM_1573736333'){
			$tableRow['data']['UF_CRM_1573736333'] = $product ?? '';
    	    $tableRow['data']['DEALUF_CRM_1573736333'] = $product ?? '';

			 /*
		    $field = $arReport['DEAL_FIELDS']['UF_CRM_1573736333'] ?? [];
    	    $tableRow['data']['UF_CRM_1573736333'] = $field['VALUE'] ?? '';
$tableRow['data']['DEALUF_CRM_1573736333'] = $field['VALUE'] ?? '';*/
		}
		 elseif($fieldCode == 'CATEGORY_ID'){

	        $field = $arReport['DEAL_FIELDS']['CATEGORY_ID'] ?? [];
			$field['VALUE'] = $field['VALUE'] == 1 ? 'Сбережения' : 'Займы';
    	    $tableRow['data']['CATEGORY_ID'] = $field['VALUE'] ?? '';
    	    $tableRow['data']['DEALCATEGORY_ID'] = $field['VALUE'] ?? '';
		}
		 else {

	        $field = $arReport['DEAL_FIELDS'][$fieldCode] ?? [];
    	    $tableRow['data']['DEAL_'.$fieldCode] = $field['VALUE'] ?? '';
		}
      }

      $tableRows[] = $tableRow;
    }
	  //	print_r($tableRows);
    $this->arResult['TABLE_ROWS'] = $tableRows;

    return $this;
  }
}
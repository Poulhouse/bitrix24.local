<?php

namespace Whatasoft\Statistic;

class CallReportFilter {
  
  protected $filter = [];
  
  public function getFilter()
  {
    return $this->filter;
  }
  
  public function setCallDateTimeFrom($strDateTime, $format = CallReport::REPORT_DATETIME_FORMAT)
  {
    $this->filter['>=UF_CALL_DATETIME'] = new \Bitrix\Main\Type\DateTime($strDateTime, $format);
    return $this;
  }
  
  public function setCallDateTimeTo($strDateTime, $format = CallReport::REPORT_DATETIME_FORMAT)
  {
    $this->filter['<=UF_CALL_DATETIME'] = new \Bitrix\Main\Type\DateTime($strDateTime, $format);
    return $this;
  }
  
  public function setIdFrom($id)
  {
    $this->filter['>=ID'] = $id;
    return $this;
  }
  
  public function setIdTo($id)
  {
    $this->filter['<=ID'] = $id;
    return $this;
  }
  
  public function setQueues(array $queueIds)
  {
    $this->filter['@UF_QUEUE_ID'] = $queueIds;
    return $this;
  }
  
  public function setStages(array $stageIds)
  {
    $this->filter['@UF_STAGE_ID'] = $stageIds;
    return $this;
  }
  
  public function setReportType($typeCode)
  {
    $this->filter['UF_REPORT_TYPE_CODE'] = $typeCode;
    return $this;
  }
  
  public function setManagerName($managerName)
  {
    $managerName = trim($managerName);
    $this->filter['%=UF_MANAGER_FULL_NAME'] = '%'.$managerName.'%';
    return $this;
  }
}
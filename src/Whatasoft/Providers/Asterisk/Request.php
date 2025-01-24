<?php

namespace Whatasoft\Providers\Asterisk;

class Request
{
  public function getApiUrl()
  {
    return ASTERISK_API_URL;
  }
  
  public function getTransferApiUrl()
  {
    return ASTERISK_TRANSFER_API_URL;
  }
  
  public function setUrlQueryParams(string $url, array $queryParams = [])
  {
    return (count($queryParams)) 
      ? $url . "?" . http_build_query($queryParams) 
      : $url;
  }
  
  public function get(array $queryParams = [])
  {
    $url = $this->setUrlQueryParams($this->getApiUrl(), $queryParams);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
    $response = curl_exec($ch); 
    curl_close($ch);      
    
    return $response;
  }
  
  public function post(array $params = [])
  {
    $url = $this->getApiUrl();
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_POST, true); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
    $response = curl_exec($ch); 
    curl_close($ch);

    return $response;
  }
  
  public function transfer_get_request(string $methodUri, array $queryParams = [])
  {
    $baseUrl = $this->getTransferApiUrl();
    $methodUrl = $baseUrl . $methodUri;
    $url = $this->setUrlQueryParams($methodUrl, $queryParams);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 
    $response = curl_exec($ch);
    curl_close($ch);      
    
    return $response;
  }
}
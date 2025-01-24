<?php

namespace Whatasoft\Helpers\Http;

use \Bitrix\Main\Application;

class Request
{
	protected $request;

	function __construct()
	{
		$this->request = Application::getInstance()->getContext()->getRequest();
	}

	public function get(string $key, $default = null)
	{
		$value = $this->getRequestValue($key);
		return (!is_null($value)) ? $value : $default;
	}

	public function exist(string $key): bool
	{
		return !is_null($this->getRequestValue($key));
	}
  
  protected function getRequestValue(string $key)
  {
    return $this->request->get($key);
  }
}
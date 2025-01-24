<?php
use \Bitrix\Main\Loader;
use \Bitrix\Main\Application;


if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
class KPlabGeneratePswd extends CBitrixComponent {
	public function executeComponent()
	{
		$this->arResult['generatePswd'] = true;
		$this -> IncludeComponentTemplate();
	}
}
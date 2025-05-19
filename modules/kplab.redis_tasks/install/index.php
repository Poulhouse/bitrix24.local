<?php
use KPLab\Redis\Tasks\Installer;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

require_once __DIR__.'/../vendor/autoload.php';

$installer = new Installer();
$installer->install();
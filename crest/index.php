<?
require_once (__DIR__.'/crest.php');
//$result = CRest::call(
//   'crm.lead.add',
//   [
//      'fields' =>[
//      'TITLE' => 'Название лида',//Заголовок*[string]
//      'NAME' => 'Имя',//Имя[string]
//      'LAST_NAME' => 'Фамилия',//Фамилия[string]
//      ]
//   ]);
   
$result = CRest::call(
	'crm.address.add', 
	[
		'fields' => [
		'TYPE_ID' => 4,
		'ENTITY_TYPE_ID' => 3,
		'ENTITY_ID' => 50399,
		'ADDRESS_1' => "Московский пр-т, 261111",
		'CITY' => "Калининград",
		]
	]);
   
   
echo '<pre>';
	print_r($result);
echo '</pre>';
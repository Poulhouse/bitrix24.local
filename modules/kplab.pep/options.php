<?php
use Bitrix\Main\Config\Option;

$moduleId = 'kplab.pep';

$arAllOptions = [
    ['pep_secret_key', 'Секретный ключ HMAC', '', ['text', 50]],
    ['pep_sms_lifetime', 'Срок жизни СМС-кода (мин)', '10', ['text', 5]],
    ['pep_verify_url', 'Ссылка на страницу проверки', 'https://testcrm.seller-capital.ru/pep/verify.php', ['text', 100]],
];

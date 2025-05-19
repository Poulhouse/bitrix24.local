<?php namespace KPLab\API\V2\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Application;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\JWT;
use KPLab\Logs;
use KPLab\API\V2\LogsAction;
use Bitrix\Main\Context;

//define("LOG_API_DASHBOARD_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/DashBoardController.log");
\Bitrix\Main\Loader::includeModule('kplab.api.v2');
class DashBoard extends \Bitrix\Main\Engine\Controller
{
    public function getDefaultPreFilters()
    {
        return [
            new \KPLab\API\V2\Auth\ActionFilter\Authentication(),
        ];
    }

    public function getDefaultPostFilters()
    {
        return array();
    }

    protected function prepareParams()
    {
        return parent ::prepareParams();
    }

    //region DBActions
    public function getDealsForPartnerAction(array $params = [])
    {
        define("LOG_API_SYNC_DEALPARTNER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/DealsForPartnerController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;
        $uf = isset($queryArray['uf']) ? $queryArray['uf'] : false;

        $objectData['ITEM_TITLE'] = "Запрос Сделок для ДБ Партнерский канал Займы: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        // Построение SQL-запросов
        $strCountDealsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_deal INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID WHERE b_crm_deal.CATEGORY_ID = '22' AND DATE(b_crm_deal.DATE_MODIFY) <= '{$endDate}' ORDER BY b_crm_deal.ID ASC;"
            : "SELECT COUNT(*) FROM b_crm_deal INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID WHERE b_crm_deal.CATEGORY_ID = '22' AND (b_crm_deal.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}')";

        $strDealSQL = $startDate === null
            ? "SELECT b_crm_deal.*, b_uts_crm_deal.*, GROUP_CONCAT(b_crm_utm.CODE) AS UTM_CODES, GROUP_CONCAT(b_crm_utm.VALUE) AS UTM_VALUES 
               FROM b_crm_deal 
               INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID 
               LEFT JOIN b_crm_utm ON b_crm_deal.ID = b_crm_utm.ENTITY_ID AND b_crm_utm.ENTITY_TYPE_ID = " . \CCrmOwnerType::Deal . " 
               WHERE b_crm_deal.CATEGORY_ID = '22' AND DATE(b_crm_deal.DATE_MODIFY) <= '{$endDate}' 
               GROUP BY b_crm_deal.ID 
               ORDER BY b_crm_deal.ID ASC 
               LIMIT {$qty} OFFSET {$offset};"
            : "SELECT b_crm_deal.*, b_uts_crm_deal.*, GROUP_CONCAT(b_crm_utm.CODE) AS UTM_CODES, GROUP_CONCAT(b_crm_utm.VALUE) AS UTM_VALUES 
               FROM b_crm_deal 
               INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID 
               LEFT JOIN b_crm_utm ON b_crm_deal.ID = b_crm_utm.ENTITY_ID AND b_crm_utm.ENTITY_TYPE_ID = " . \CCrmOwnerType::Deal . " 
               WHERE b_crm_deal.CATEGORY_ID = '22' AND (b_crm_deal.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}') 
               GROUP BY b_crm_deal.ID 
               ORDER BY b_crm_deal.ID ASC 
               LIMIT {$qty} OFFSET {$offset};";
        $resCountItemsQuery = $DB->query($strCountDealsSQL);
        $totalItems = $resCountItemsQuery->Fetch()['COUNT(*)'];

        // Выполнение запроса
        $resItemsQuery = $DB->query($strDealSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_DEAL', \CCrmOwnerType::Deal);
        $arItems = [
            'object' => 'deals',  // Указываем объект
            'results' => []       // Инициализируем массив для результатов
        ];

        $dealData = [];

        while ($resItem = $resItemsQuery->Fetch()) {
            $dealId = $resItem['ID'];

            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = [];
            $includedTitles = [
                'ID',
                'Название',
                'Тип',
                'Воронка',
                'Стадия сделки',
                'Группа стадии',
                'Новая сделка',
                'Валюта',
                'Сумма',
                'IS_MANUAL_OPPORTUNITY',
                'Компания',
                'Контакт',
                'Контакты',
                'Дата начала',
                'Дата завершения',
                'Доступна для всех',
                'Закрыта',
                'Ответственный',
                'Кем изменена',
                'MOVED_BY_ID',
                'Дата создания',
                'Дата изменения',
                'MOVED_TIME',
                'Источник',
                'Дополнительно об источнике',
                'Лид',
                'Дополнительная информация',
                'Внешний источник',
                'Идентификатор элемента во внешнем источнике',
                'Рекламная система',
                'Тип трафика',
                'Обозначение рекламной кампании',
                'Содержание кампании',
                'Условие поиска кампании',
                'Расторжение договора сбережения',
                'SC - Сопровождение займов Селлеров',
                'Договор кредитной линии',
                'Частичное изъятие по договору сбережения',
                'SC - Повторный транш',
                'ОРД - Маркетинг',
                'SCP - Андеррайтинг',
                'СЗИ',
                'SCP Кредитный Комитет',
                'СМАРТ ОВК',
                'SCP - Выплата Агентского вознаграждения',
                '(КПК) Контрактное финансирование',
                'Кредитный комитет КПК',
                'SC - Финконтроль',
                'Верификация клиентов Seller-Capital',
                'Сопровождение депозитов МКК/КПК',
                'Договор сбережения',
                '(В разработке) Опросы и тестирование',
                'LAST_ACTIVITY_TIME',
                'LAST_ACTIVITY_BY',
                'Причины отказа SCP',
                'utm_content',
                'Тип клиента',
                'Данные о партнере реферале',
                'Новый ЛИД, дата (SC-ЛИДЫ)',
                'Cold box (депозит-юл), дата',
                'Переговоры с ЛПР (депозит-юл), дата',
                'Клиенты с сайта (депозит-юл), дата',
                'Специалист, дата (SC-ЛИДЫ)',
                'КП (депозит-юл), дата',
                'Успешный ЛИД, дата (SC-ЛИДЫ)',
                'Успешный ЛИД, сумм (SC-ЛИДЫ)',
                'Некачественный ЛИД, дата (SC-ЛИДЫ)',
                'Для отчета',
                'Результат Скоринг системы «Seller-Engine»',
                'Предварительно одобренный кредитный лимит / ВКЛ',
                'Сумма депозита',
                'Срок займа / размещения',
                '% Ставка (в зависимости от условия)',
                'Как происходят выплаты % (ежемесячно или в конце срока размещения)',
                'Сбор документов (депозит-юл), дата',
                'Проверка ОВК (депозит-юл), дата',
                'Подписание Договора (депозит-юл), дата',
                'Договор подписан (депозит-юл), дата',
                'Депозит оформлен (депозит-юл), дата',
                'Отказ компании(депозит-юл), дата',
                'Архив-Резерв (депозит-юл), дата',
                'Сбор документов, дата',
                'Сбор документов, сумм',
                'Новый заем, дата',
                'Акция Seller Capital',
                'Переговоры с ЛПР, дата',
                'Переговоры с ЛПР, сумм',
                'Передано на Андеррайтинг, дата',
                'Передано на Андеррайтинг, сумм',
                'Сбор пакета документов, дата',
                'Сбор пакета документов, сумм',
                'Проверка ОВК, дата',
                'Проверка ОВК, сумм',
                'Подписание договора, дата',
                'Подписание договора, сумм',
                'Финансирование, дата',
                'Финансирование, сумм',
                'Финансирование выдано, дата',
                'Финансирование выдано, сумм',
                'Срок ВКЛ / займа (дни) SC',
                'ID копии',
                'Сделка провалена, дата',
                'Сделка провалена, сумм',
                'Отказ СЗИ, дата',
                'Отказ СЗИ, сумм',
                'Отказ Андеррайтинг, дата',
                'Отказ Андеррайтинг, сумм',
                'Отказ клиента (После КК), дата',
                'Отказ клиента (После КК), сумм',
                'Дата посещения',
                'Номер заявки',
                'Дата заявки',
                'Дата принятия решения',
                'Номер договора',
                'Дата договора',
                'Деньги выданы',
                'Сумма займа по договору',
                'Деньги получены',
                'Номер агентского договора',
                'Дата агентского договора',
                'РСП наблюдатель',
                'Номер сделки',
                'Cold Box, сумм (SP-вовлечение)',
                'Звонок №1, сумм (SP-вовлечение)',
                'Звонок №1, дата (SP-вовлечение)',
                'WhatsApp, сумм (SP-вовлечение)',
                'WhatsApp, дата (SP-вовлечение)',
                'E-mail, сумм (SP-вовлечение)',
                'E-mail, дата (SP-вовлечение)',
                'Звонок №2, сумм (SP-вовлечение)',
                'Звонок №2, дата (SP-вовлечение)',
                'Специалист, сумм (SP-вовлечение)',
                'Специалист, дата (SP-вовлечение)',
                'Успешный, сумм (SP-вовлечение)',
                'Успешный, дата (SP-вовлечение)',
                'Верификация, сумм (SP-вовлечение)',
                'Верификация, дата (SP-вовлечение)',
                'Буфер, сумм (SP-вовлечение)',
                'Буфер, дата (SP-вовлечение)',
                'Архив-Резерв, сумм (SP-вовлечение)',
                'Архив-Резерв, дата (SP-вовлечение)',
                'В работе, дата (SP-вовлечение)',
                'В работе, сумм (SP-вовлечение)',
                'Звонок №1, конв. (SP-вовлечение)',
                'WhatsApp, конв. (SP-вовлечение)',
                'E-mail, конв. (SP-вовлечение)',
                'Звонок №2, конв. (SP-вовлечение)',
                'Специалист, конв. (SP-вовлечение)',
                'Дата заявки (Оформление займа Селлерам)',
                'Цель займа подробная (Оформление займа Селлерам)',
                'Дата договора займа(Оформление займа Селлерам)',
                'ID Андеррайтинга',
                'Ежемесячный оборот',
                'SCP-КВ (SCP)',
                'Результат проверки ОВК / СЗИ',
                'Комментарий специалиста ОВК / СЗИ',
                'Вид займа Селлеру (без отсрочки/с отсрочкой)',
                'Тарифный план займа',
                'Срок займа',
                'Сумма к снятию',
                'Кредитор',
                'Поручитель №1',
                'Поручитель №1 - % поручительства',
                'Поручитель №2',
                'Поручитель №2 - % поручительства',
                'Объект залога',
                'Залоговая стоимость',
                'Клиент согласен с предварительными условиями?',
                'SCP - Ревизоры',
                'Ожидаемая дата получения займа',
                'Лимит кредитной линии / ВКЛ',
                'Вид сбережения',
                'Срок сбережения',
                'Подписание Nopaper',
                'Сервис ЭДО для подписания',
                'Нет Инфо, сумм (SP-вовлечение)',
                'Нет Инфо, дата (SP-вовлечение)',
                'Целевой, сумм (SP-вовлечение)',
                'Целевой, дата (SP-вовлечение)',
                'Дата активности (SP-вовлечение)',
                'Дата активности (SC-ЛИДЫ)',
                'Дата активности (SС-Займ)',
                'Стадия отказа (SC)',
                'Окончательное решение КК',
                'Cold Box, дата (SP-вовлечение)',
                'Отказ по сроку, сумм',
                'Отказ по сроку, дата',
                'Повторный скоринг (SC-лиды)',
                'Звонок №1, сумм (SC-лиды)',
                'Целевой ОК, сумм (SC-лиды)',
                'Whatsap, сумм (SC-лиды)',
                'E-mail, сумм (SC-лиды)',
                'Звонок №2, сумм (SC-лиды)',
                'Специалист, сумм (SC-лиды)',
                'Успешный из буфера (SC-лиды)',
                'Конв. звонок №1 (SC-лиды)',
                'Конв. whatsap (SC-лиды)',
                'Конв. e-mail (SC-лиды)',
                'Конв. звонок №2 (SC-лиды)',
                'Конв. специалист (SC-лиды)',
                'Доработка, м',
                'Доработка, сумм',
                'Доработка, дата',
                'Дата последнего успешного звонка',
                'Смарт вступление в Аистенок',
                'Вступление в кооператив (смарт)',
                'Лимиты скоринга',
                'Ссылка/и на сайт (Для баннера) (SCP)',
                'id заявки в орд',
                'Формат посещения офиса',
                'Посмотреть в Тендерплане',
                'ИНН заказчика',
                'ID ответственного (мат.метрики)',
                'Тип сделки',
                'Новый ЛИД, сумм (SC-ЛИДЫ)',
                'Буфер, сумм (SC-ЛИДЫ)',
                'Нет информации о клиенте, сумм (SC-ЛИДЫ)',
                'Некачественный ЛИД, сумм (SC-ЛИДЫ)',
                'Успешный ЛИД, сумм (SC-ЛИДЫ)',
                'SC - Договор займа',
                'Дата начала',
                'Дата события',
                'Лид',
                'Описание события',
                'Предполагаемая дата закрытия',
                'Сделка закрыта',
                'Сумма в валюте учета',
                'Валюта учета',
                'Тип',
                'Тип события',
                'Стадия сделки',
                'Телефон №1',
                'Поручители',
                'COMMENTS',
                'График',
                'Дата',
                'Дата запуска процесса',
                'Дата изменения элемента',
                'Город (old) обязательное',
                'ИНН',
                'ИНН №1',
                'Срок размещения',
                'Собственные средства',
                'NEW ЛИД, дата (лид) удалить',
                'Дата + 1 месяц (не удалять)',
                'Дата смарт "Сопровождение" (не удалять)',
                'Должность',
                'Смарт ОВК',
                'Фамилия',
                'Имя',
                'Отчество',
                'Телефон',
                'E-mail',
                'Город (old)',
                'Тип проверки СЗИ',
                'Результат проверки СЗИ',
                'Рабочее время, новый заем',
                'Рабочее время, исходящий/буферный поток',
                'Рабочее время, переговоры с ЛПР',
                'Рабочее время, сбор документов',
                'Рабочее время, доработка',
                'Рабочее время, передано на Андеррайтинг',
                'Рабочее время, сбор пакета документов',
                'Рабочее время, вступление в Аистенок',
                'Рабочее время, вступление в кооператив',
                'Рабочее время, проверка ОВК',
                'Рабочее время, подписание договора',
                'Рабочее время, финансирование',
                'Рабочее время, передано на сопровождение',
                'Рабочее время, отказ клиента до КК',
                'Рабочее время, отказ по сроку до КК',
                'Рабочее время, отказ по сроку после КК',
                'Рабочее время, отказ СЗИ',
                'Рабочее время, отказ Андеррайтинг',
                'Рабочее время, отказ клиента после КК',
                'Начальная дата метрики',
                'Исходящий/буферный поток, дата',
                'Вступление в Аистенок, дата',
                'Предыдущая стадия',
                'Текущая стадия',
                'Организация Содействие',
                'Запрашиваемая сумма',
                'Плановая дата сделки',
                'Подписант',
                'Комментарий к договору займа',
                'Дата сделки ДКП',
                'Причина отказа',
                'Для ИП / Юр.Лиц: API–ключ',
                'Заявка',
                'Сделка с родственниками',
                'Причина отказа по заявке на заем (подробно)',
                'Адрес залога',
                'Документ ППС',
                'Запись в ЕГРП документа ППС',
                'Запись в ЕГРП, номер договора',
                'Запись в ЕГРП, дата договора',
                'Доля собственности залога',
                'Адрес объекта недвижимости',
                'Кем перенесена на Потенциальный партнер',
                'Скор-балл',
                'Uid Заявки',
                'Филиал',
                'Просрочена'
            ];

            // Подготавливаем данные сделки
            $dealData = $this->prepareDataArray($resItem, $fieldsMetadata, $excludedTitles, $includedTitles, $uf, false);
            $dealData['utm_data'] = [];

            // Обработка UTM-меток, если они есть
            if (!empty($resItem['UTM_CODES']) && !empty($resItem['UTM_VALUES'])) {
                $utmCodes = explode(',', $resItem['UTM_CODES']);
                $utmValues = explode(',', $resItem['UTM_VALUES']);

                // Формируем массив UTM-меток
                foreach ($utmCodes as $index => $code) {
                    $dealData['utm_data'][$code] = $utmValues[$index] ?? null;
                }
            }// Добавляем сделку в результаты
            $arItems['results'][] = $dealData;
        }

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getUnderwritingItemsAction(array $params = [])
    {
        define("LOG_API_SYNC_UNDERWRITING_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/UnderwritingController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        Logs\File::AddMessage($url, "url", LOG_API_SYNC_UNDERWRITING_CONTROLLER);
        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта Андерайтинг: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arUnderwritingItems = [];

        // Построение SQL-запросов
        $strCountUnderwritingItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_149 WHERE DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_149 WHERE UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}'";

        $strUnderwritingItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_149 WHERE DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_149 WHERE UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountUnderwritingItemsQuery = $DB->query($strCountUnderwritingItemsSQL);
        $totalUnderwritingItems = $resCountUnderwritingItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arUnderwritingItems['object'] = (string) "underwriting";
        $resUnderwritingItemsQuery = $DB->query($strUnderwritingItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_49', 149);

        while ($resUnderwritingItem = $resUnderwritingItemsQuery->Fetch()) {

            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = [
                'Доступно для всех',
                'Создано CRM-формой',
                'Дополнительно об источнике',
                'Режим расчёта суммы',
                'Филиал-тип список (удалить)',
                'Заявка на Кредитный комитет',
                'Комментарий',
                'Поручитель №1 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №2 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №2 - Согласие на обработку ПД',
                'Согласие членов семьи на объект залога',
                'Полный пакет документов для проверки',
                'Сумма (желаемая/указанная клиентом) - удалить',
                'Комментарий СЗИ',
                'Предварительные условия займа',
                'Комментарий клиента по предварительным условиям (Удалить)',
                'Поручитель №3 - % поручительства',
                'Поручитель №4 - % поручительства',
                'Поручитель №5 - % поручительства',
                'Поручитель №6 - % поручительства',
                'Поручитель №3 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №4 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №5 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №6 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №3 - Согласие на обработку ПД',
                'Поручитель №4 - Согласие на обработку ПД',
                'Поручитель №5 - Согласие на обработку ПД',
                'Поручитель №6 - Согласие на обработку ПД',
                'Смарт заявка на заем (не актуальная)',
                'Доработка со стороны Андеррайтинг (мат.метрики)',
                'Доработка со стороны СЗИ (мат.метрики)',
                'Согласование предварительных условий (мат.метрики)',
                'New заявка → Старт проверки СЗИ (мат.метрики)',
                'Формирование предусловий (мат.метрики)',
                'Формирование заявки на КК (мат.метрики)',
                'Обработка решения КК (мат.метрики)',
                'Время КК (мат.метрики)',
                'ID специалиста Андеррайтинг (мат.метрики)',
                'Проверка СЗИ (мат.метрики)',
                'ID специалиста СЗИ (мат.метрики)',
                'Ч КК Андеррайтинг (регламент)',
                'Ч Обработка решения КК (регламент)',
                'Ч Андеррайтинг полностью (регламент)',
                '✭Формирование заявки на КК (регламент)',
                '✭Формирование Пред. условий (регламент)',
                'Тело запроса в SE (dataJson)',
                'ID Задачи в SE (taskId)',
                'ФССП (к удалению)',
                'Среднемесячная выручка (к удалению)',
                'Выручка за последний месяц (к удалению)',
                'Остаток товаров (к удалению)',
                'Наличие просроченных платежей (к удалению)',
                'Новый лимит с учетом сглаживания (к удалению)',
                'ПДН (к удалению)',
                'Лимит скоринг системы «Seller-Engine» (к удалению)',
                'Сумма займа (транша) (т10)',
                'Сумма займа (транша) (т2)',
                'Сумма займа (транша) (т3)',
                'Сумма займа (транша) (т4)',
                'Сумма займа (транша) (т5)',
                'Сумма займа (транша) (т6)',
                'Сумма займа (транша) (т7)',
                'Сумма займа (транша) (т8)',
                'Сумма займа (транша) (т9)',
                'Error',
                'ExportJSON',
                'ExportResult',
                'ImportJson',
                'ImportResult',
                'jwtToken',
                'jwtTokenDateTime',
                'refreshToken',
                'Для ИП / Юр.Лиц: API–ключ',
                'Протокол КК',
                'Файл скоринга',
                'Проверка паспорта'
            ];
            $res = $this->prepareDataArray($resUnderwritingItem, $fieldsMetadata, $excludedTitles,[],false,false);
            $arUnderwritingItems['results'][] = $res;
        }

        $totalPages = ceil($totalUnderwritingItems / $qty);
        $arUnderwritingItems['total'] = (int) $totalUnderwritingItems;
        $arUnderwritingItems['total_pages'] = (int) $totalPages;
        $arUnderwritingItems['has_more'] = ($offset + $qty < $totalUnderwritingItems);

        $jsonRes = [
            'success' => $arUnderwritingItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getOTKItemsAction(array $params = [])
    {
        define("LOG_API_SYNC_OTK_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/OTKController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта ОТК: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arUnderwritingItems = [];

        // Построение SQL-запросов
        $strCountUnderwritingItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_176 WHERE CATEGORY_ID = '0' AND DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_176 WHERE CATEGORY_ID = '0' AND UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}'";

        $strUnderwritingItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_176 WHERE CATEGORY_ID = '0' AND DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_176 WHERE CATEGORY_ID = '0' AND UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountUnderwritingItemsQuery = $DB->query($strCountUnderwritingItemsSQL);
        $totalUnderwritingItems = $resCountUnderwritingItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arUnderwritingItems['object'] = (string) "otk";
        $resUnderwritingItemsQuery = $DB->query($strUnderwritingItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_60', 176);

        while ($resUnderwritingItem = $resUnderwritingItemsQuery->Fetch()) {


            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = [
                'Внешний код',
                'Доступно для всех',
                'Создано CRM-формой',
                'Наблюдатели',
                'Кем осуществлена последняя активность в таймлайне',
                'Последняя активность',
                'Категория бизнес-процесса',
                'Лидогенерация.Описание риска',
                'Первичные выдачи.Описание риска',
                'Вторичные выдачи.Описание риска',
                'ЛК клиента.Описание риска',
                'Андеррайтинг.Описание риска',
                'КК.Описание риска',
                'Выдача займа.Описание риска',
                'Погашение займа.Описание риска',
                'Продажи.Описание риска',
                'Мониторинг клиентов.Описание риска',
                'Досудебное взыскание.Описание риска',
                'Бухгалтерия.Описание риска',
                'Судебное взыскание.Описание риска',
                'Риски директора.Описание риска',
                'HR.Описание риска',
                'Риски акционеров.Описание риска',
                'Риск-событие',
                'Последствия реализации рисков',
                'Вид риска (ГК)',
                'Риск-событие.Невыполнение плана продаж сотрудником',
                'Риск-событие.Ошибки топ-менеджмента',
                'Риск-событие.МК.Возникновение просрочки',
                'Риск-событие.Дальнейшая невозможность взыскания',
                'Риск событие.Бухгалтерия.Возникновение просрочки',
                'Риск событие.Риски директора.Ошибки топ-менеджмента',
                'Причина реализации рисков',
                'Проведение мероприятия /процедуры реагирования на риск',
                'ФИО сотрудника(заполнявшего форму)',
                'ФИО руководителя(заполнявшего форму сотрудника)',
                'ID Сотрудника(заполняющего форму Реестр рисков)',
                'Описание решения',
                'Документация для тестирования',
                'Заказчик',
                'Ссылка на процесс тестирования',
                'Ошибки при тестировании',
                'Дата создания документа',
                'Краткое описание проекта(какая у него была цель, какие задачи решал)',
                'Техническая документация',
                'Бизнес документация',
                'Обучающие материалы',
                'Значимость',
                'Вероятность, %',
                'Уровень риска',
                'Лицо, ответственное за мероприятия по управлению риском',
                'NEW сообщение',
                'Текущее сообщение',
                'Дата последней корректировки',
                'Название переменной',
                'Канал отправки',
                'Получатель сообщения',
                'Предыдущий канал отправки',
                'Ссылки на элементы ошибки',
                'Скриншоты и документы',
                'Отчет по устранению (текст)',
                'Отчет по решению (документы и вложения)',
                'Все ошибки (не исп)',
                'БАГ в БП',
                'БАГ специалистов в работе в БП',
                'БАГ в БП (кол-во)',
                'БАГ специалистов в работе в БП (кол-во)',
                'Поле буфер',
                'ID ответственногоА (Не исп)',
                'Текущий месяцА (Не исп)',
                'Комментарий',
                'Предложений поступило (кол-во)',
                'Поступившие предложения',
                'Нарушение регламентов специалистами (old)',
                'Ссылка на элемент ошибки (только регламент)',
                'Ошибка',
                'Описание риска. Внутренний фрод',
                'Вид риска (SC)'
            ];
            $res = $this->prepareDataArray($resUnderwritingItem, $fieldsMetadata, $excludedTitles,[],false,false);
            $arUnderwritingItems['results'][] = $res;
        }

        $totalPages = ceil($totalUnderwritingItems / $qty);
        $arUnderwritingItems['total'] = (int) $totalUnderwritingItems;
        $arUnderwritingItems['total_pages'] = (int) $totalPages;
        $arUnderwritingItems['has_more'] = ($offset + $qty < $totalUnderwritingItems);

        $jsonRes = [
            'success' => $arUnderwritingItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getServiceAppealsItemsAction(array $params = [])
    {
        define("LOG_API_SYNC_OTK_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/DB_ServiceAppeals.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта Сервиса обращений: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        // Построение SQL-запросов
        $strCountItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_180 WHERE DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_180 WHERE UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}'";

        $strItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_180 WHERE DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_180 WHERE UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountItemsQuery = $DB->query($strCountItemsSQL);
        $totalItems = $resCountItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arItems['object'] = "serviceAppeals";
        $resItemsQuery = $DB->query($strItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_45', 180);

        while ($resUnderwritingItem = $resItemsQuery->Fetch()) {
            // Массив заголовков полей, которые нужно исключить из обработки
            /*$excludedTitles = [
                'Внешний код',
                'Доступно для всех',
                'Создано CRM-формой',
                'Наблюдатели',
                'Кем осуществлена последняя активность в таймлайне',
                'Последняя активность',
                'Категория бизнес-процесса',
                'Лидогенерация.Описание риска',
                'Первичные выдачи.Описание риска',
                'Вторичные выдачи.Описание риска',
                'ЛК клиента.Описание риска',
                'Андеррайтинг.Описание риска',
                'КК.Описание риска',
                'Выдача займа.Описание риска',
                'Погашение займа.Описание риска',
                'Продажи.Описание риска',
                'Мониторинг клиентов.Описание риска',
                'Досудебное взыскание.Описание риска',
                'Бухгалтерия.Описание риска',
                'Судебное взыскание.Описание риска',
                'Риски директора.Описание риска',
                'HR.Описание риска',
                'Риски акционеров.Описание риска',
                'Риск-событие',
                'Последствия реализации рисков',
                'Вид риска (ГК)',
                'Риск-событие.Невыполнение плана продаж сотрудником',
                'Риск-событие.Ошибки топ-менеджмента',
                'Риск-событие.МК.Возникновение просрочки',
                'Риск-событие.Дальнейшая невозможность взыскания',
                'Риск событие.Бухгалтерия.Возникновение просрочки',
                'Риск событие.Риски директора.Ошибки топ-менеджмента',
                'Причина реализации рисков',
                'Проведение мероприятия /процедуры реагирования на риск',
                'ФИО сотрудника(заполнявшего форму)',
                'ФИО руководителя(заполнявшего форму сотрудника)',
                'ID Сотрудника(заполняющего форму Реестр рисков)',
                'Описание решения',
                'Документация для тестирования',
                'Заказчик',
                'Ссылка на процесс тестирования',
                'Ошибки при тестировании',
                'Дата создания документа',
                'Краткое описание проекта(какая у него была цель, какие задачи решал)',
                'Техническая документация',
                'Бизнес документация',
                'Обучающие материалы',
                'Значимость',
                'Вероятность, %',
                'Уровень риска',
                'Лицо, ответственное за мероприятия по управлению риском',
                'NEW сообщение',
                'Текущее сообщение',
                'Дата последней корректировки',
                'Название переменной',
                'Канал отправки',
                'Получатель сообщения',
                'Предыдущий канал отправки',
                'Ссылки на элементы ошибки',
                'Скриншоты и документы',
                'Отчет по устранению (текст)',
                'Отчет по решению (документы и вложения)',
                'Все ошибки (не исп)',
                'БАГ в БП',
                'БАГ специалистов в работе в БП',
                'БАГ в БП (кол-во)',
                'БАГ специалистов в работе в БП (кол-во)',
                'Поле буфер',
                'ID ответственногоА (Не исп)',
                'Текущий месяцА (Не исп)',
                'Комментарий',
                'Предложений поступило (кол-во)',
                'Поступившие предложения',
                'Нарушение регламентов специалистами (old)',
                'Ссылка на элемент ошибки (только регламент)',
                'Ошибка',
                'Описание риска. Внутренний фрод',
                'Вид риска (SC)'
            ];
            */
            $res = $this->prepareDataArray($resUnderwritingItem, $fieldsMetadata, [],[],false,false);
            $arItems['results'][] = $res;
        }

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getOSKItemsAction(array $params = [])
    {
        define("LOG_API_SYNC_OSK_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/OSKController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта ОСК: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        // Построение SQL-запросов
        $strCountItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_134 WHERE DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_134 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}')";

        $strItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_134 WHERE DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_134 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountItemsQuery = $DB->query($strCountItemsSQL);
        $totalItems = $resCountItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arItems['object'] = (string) "osk";
        $resItemsQuery = $DB->query($strItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_56', 134);

        while ($resItem = $resItemsQuery->Fetch()) {


            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = [
                'Внешний код',
                'Создано CRM-формой',
                'Компания',
                'Контакт',
                'CONTACT_IDS',
                'CONTACTS',
                'Наблюдатели',
                'Валюта',
                'Реквизиты вашей компании',
                'Кем осуществлена последняя активность в таймлайне',
                'Личный кабинет Seller Capital',
                'Договор займа',
                'Был в массовом запуске?',
                'Дата и время последней синхронизации',
                'Создать заявку на повторный транш!',
                'Дата + м (удалить ЩАС)',
                'Дата 1 число (удалить ЩАС)',
                'Для ИП / Юр.Лиц: Ссылки на магазины, на всех маркетплейсах',
                'Для ИП / Юр.Лиц: API–ключ',
                'Для ИП / Юр.Лиц: Банковская выписка за 12 месяцев по всем расчетным и номинальным счетам',
                'Для ИП: Цветная копия паспорта (все страницы) / Для Юр.Лиц: Цветные копии паспорта руководителя (представителя) и бенефициара (все страницы)',
                'Согласие на обработку персональных данных',
                'Для Юр.Лиц: Оборотно-сальдовые ведомости по счетам 66, 67 и 76 с начала года по текущую дату (с расшифровками в разрезе контрагентов)',
                'Для ИП / Юр.Лиц: Бухгалтерская отчетность и налоговая декларация',
                'Для Юр.Лиц: Копия решения о создании юридического лица',
                'Для Юр.Лиц: Копия действующего решения об избрании единоличного исполнительного органа',
                'Для Юр.Лиц: Копия бухгалтерской отчётности',
                'Для Юр.Лиц: Копии имеющихся лицензий',
                'Поручитель №1 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №1 - Согласие на обработку ПД',
                'Поручитель №2 - Согласие на обработку ПД',
                'Согласие членов семьи на объект залога',
                'Документы на объект залога',
                'Поручитель №3 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №4 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №5 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №6 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №3 - Согласие на обработку ПД',
                'Поручитель №4 - Согласие на обработку ПД',
                'Поручитель №5 - Согласие на обработку ПД',
                'Поручитель №6 - Согласие на обработку ПД',
                'Поручитель №3 - % поручительства',
                'Поручитель №4 - % поручительства',
                'Поручитель №5 - % поручительства',
                'Поручитель №6 - % поручительства',
                'Смарт Аистенок',
                'Смарт Вступление в КПК',
                'ТЕСТ_ТелоЗапроса_БП',
                'ТЕСТ_taskId_SE',
                'Остаток товаров',
                'ПДН',
                'Наличие просроченных платежей',
                'ФССП',
                'Показывать кнопку «Повторный транш»?',
                'Количество членов в группе',
                'Error',
                'ExportJSON',
                'ExportResult',
                'ImportJson',
                'ImportResult',
                'SC_AvailableLimit',
                'SC_FullName',
                'SC_Inn',
                'SC_Status',
                'jwtToken',
                'jwtTokenDateTime',
                'refreshToken',
                'Верификация',
                'ИНН Старое поле (запрос в БД) НЕ УДАЛЯТЬ',
                'Результаты ответа от SE',
                'Смарт сопровождение',
                'Телефон',
                'История отработки'
            ];
            $res = $this->prepareDataArray($resItem, $fieldsMetadata, $excludedTitles,[],false,false);
            $arItems['results'][] = $res;
        }

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getORDItemsAction(array $params = [])
    {
        define("LOG_API_SYNC_ORD_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/ORDController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта ОСК: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        // Построение SQL-запросов
        $strCountItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_148 WHERE DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_148 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}')";

        $strItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_148 WHERE DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_148 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountItemsQuery = $DB->query($strCountItemsSQL);
        $totalItems = $resCountItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arItems['object'] = (string) "ord";
        $resItemsQuery = $DB->query($strItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_73', 148);

        while ($resItem = $resItemsQuery->Fetch()) {


            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = [
                'Внешний код',
                'Создано CRM-формой',
                'Компания',
                'Контакт',
                'CONTACT_IDS',
                'CONTACTS',
                'Наблюдатели',
                'Валюта',
                'Реквизиты вашей компании',
                'Кем осуществлена последняя активность в таймлайне',
                'Личный кабинет Seller Capital',
                'Договор займа',
                'Был в массовом запуске?',
                'Дата и время последней синхронизации',
                'Создать заявку на повторный транш!',
                'Дата + м (удалить ЩАС)',
                'Дата 1 число (удалить ЩАС)',
                'Для ИП / Юр.Лиц: Ссылки на магазины, на всех маркетплейсах',
                'Для ИП / Юр.Лиц: API–ключ',
                'Для ИП / Юр.Лиц: Банковская выписка за 12 месяцев по всем расчетным и номинальным счетам',
                'Для ИП: Цветная копия паспорта (все страницы) / Для Юр.Лиц: Цветные копии паспорта руководителя (представителя) и бенефициара (все страницы)',
                'Согласие на обработку персональных данных',
                'Для Юр.Лиц: Оборотно-сальдовые ведомости по счетам 66, 67 и 76 с начала года по текущую дату (с расшифровками в разрезе контрагентов)',
                'Для ИП / Юр.Лиц: Бухгалтерская отчетность и налоговая декларация',
                'Для Юр.Лиц: Копия решения о создании юридического лица',
                'Для Юр.Лиц: Копия действующего решения об избрании единоличного исполнительного органа',
                'Для Юр.Лиц: Копия бухгалтерской отчётности',
                'Для Юр.Лиц: Копии имеющихся лицензий',
                'Поручитель №1 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №1 - Согласие на обработку ПД',
                'Поручитель №2 - Согласие на обработку ПД',
                'Согласие членов семьи на объект залога',
                'Документы на объект залога',
                'Поручитель №3 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №4 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №5 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №6 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №3 - Согласие на обработку ПД',
                'Поручитель №4 - Согласие на обработку ПД',
                'Поручитель №5 - Согласие на обработку ПД',
                'Поручитель №6 - Согласие на обработку ПД',
                'Поручитель №3 - % поручительства',
                'Поручитель №4 - % поручительства',
                'Поручитель №5 - % поручительства',
                'Поручитель №6 - % поручительства',
                'Смарт Аистенок',
                'Смарт Вступление в КПК',
                'ТЕСТ_ТелоЗапроса_БП',
                'ТЕСТ_taskId_SE',
                'Остаток товаров',
                'ПДН',
                'Наличие просроченных платежей',
                'ФССП',
                'Показывать кнопку «Повторный транш»?',
                'Количество членов в группе',
                'Error',
                'ExportJSON',
                'ExportResult',
                'ImportJson',
                'ImportResult',
                'SC_AvailableLimit',
                'SC_FullName',
                'SC_Inn',
                'SC_Status',
                'jwtToken',
                'jwtTokenDateTime',
                'refreshToken',
                'Верификация',
                'ИНН Старое поле (запрос в БД) НЕ УДАЛЯТЬ',
                'Результаты ответа от SE',
                'Смарт сопровождение',
                'Телефон',
                'История отработки'
            ];
            $res = $this->prepareDataArray($resItem, $fieldsMetadata, $excludedTitles,[],false,false);
            $arItems['results'][] = $res;
        }

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getPartnerSupportItemsAction(array $params = [])
    {
        //define("LOG_API_SYNC_FINCONTROL_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/FincontrolController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта Сопровождение партнеров: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arFincontrolItems = [];

        // Построение SQL-запросов
        $strCountUnderwritingItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_1054 WHERE DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_1054 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}')";

        $strUnderwritingItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_1054 WHERE DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_1054 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountUnderwritingItemsQuery = $DB->query($strCountUnderwritingItemsSQL);
        $totalUnderwritingItems = $resCountUnderwritingItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arFincontrolItems['object'] = (string) "partnerSupport";
        $resFincontrolItemsQuery = $DB->query($strUnderwritingItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_87', 1054);

        while ($resFincontrolItem = $resFincontrolItemsQuery->Fetch()) {
            $excludedTitles = [];
            $res = $this->prepareDataArray($resFincontrolItem, $fieldsMetadata, $excludedTitles,[],false,false);
            $arFincontrolItems['results'][] = $res;
        }

        $totalPages = ceil($totalUnderwritingItems / $qty);
        $arFincontrolItems['total'] = (int) $totalUnderwritingItems;
        $arFincontrolItems['total_pages'] = (int) $totalPages;
        $arFincontrolItems['has_more'] = ($offset + $qty < $totalUnderwritingItems);

        $jsonRes = [
            'success' => $arFincontrolItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getPartnerReferallsAction(array $params = [])
    {
        //define("LOG_API_SYNC_ORD_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/ORDController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');
        \Bitrix\Main\Loader::includeModule('iblock');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        // Если startDate передан
        $startDate = isset($queryArray['startDate']) ? date('d.m.Y 00:00:00', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('d.m.Y 23:59:59', strtotime($queryArray['endDate'])) : date('d.m.Y 23:59:59');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос списка Партнеры-рефералы: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        $arSelect = ['ID','NAME','CODE','ACTIVE_DATE','ACTIVE','IBLOCK_ID','IBLOCK_TYPE_ID','DATE_CREATE'];
        $arOrder = ['ID' => 'ASC'];

        $arFilter = $startDate === null
            ? array(
                "IBLOCK_ID" => 166,
                "ACTIVE_DATE" => "Y",
                "ACTIVE" => "Y",
                "<=DATE_CREATE" => $endDate
            )
            : array(
                "IBLOCK_ID" => 166,
                "ACTIVE_DATE" => "Y",
                "ACTIVE" => "Y",
                "<=DATE_CREATE" => $endDate,
                ">=DATE_CREATE" => $startDate
            );


        $res = \CIBlockElement::GetList($arOrder, $arFilter, false, array(), $arSelect);

        $result = array();

        // Получение элементов
        $arItems['object'] = (string) "referralList";

        while($el = $res->GetNextElement())
        {

            $arFields = $el->GetFields();
            $arProps = $el->GetProperties();
            $SUM_SCP_KB = 0;
            $SUM_DEALS = 0;
            if($arProps['SUM_SCP_KB']['VALUE'] !== "") $SUM_SCP_KB = str_replace('|RUB', '', $arProps['SUM_SCP_KB']['VALUE']);
            if($arProps['SUM_DEALS']['VALUE'] !== "") $SUM_DEALS = str_replace('|RUB', '', $arProps['SUM_DEALS']['VALUE']);

            $arProp['ASSIGN'] = $arProps['ASSIGN']['VALUE'];
            $arProp['COUNT_DEALS'] = $arProps['COUNT_DEALS']['VALUE'];
            $arProp['COUNT_LEADS'] = $arProps['COUNT_LEADS']['VALUE'];
            $arProp['ID_REFERRAL'] = $arProps['ID_REFERRAL']['VALUE'];
            $arProp['ID_SDELKI_SCP'] = $arProps['ID_SDELKI_SCP']['VALUE'];
            $arProp['INN_REFERRAL'] = $arProps['INN_REFERRAL']['VALUE'];
            $arProp['QR_KOD'] = $arProps['QR_KOD']['VALUE'];
            $arProp['QR_LINK'] = $arProps['QR_LINK']['VALUE'];
            $arProp['REFERRAL_LINK'] = $arProps['REFERRAL_LINK']['VALUE'];
            $arProp['SCP_KB'] = $arProps['SCP_KB']['VALUE'];
            $arProp['STATUS_PARTNERA'] = ['id' => $arProps['STATUS_PARTNERA']['VALUE_ENUM_ID'],'value'=>$arProps['STATUS_PARTNERA']['VALUE_ENUM']];
            $arProp['SUM_SCP_KB'] = number_format($SUM_SCP_KB,2,'.',' ');
            $arProp['SUM_DEALS'] = number_format($SUM_DEALS,2,'.',' ');
            $result[] = array_merge($arProp, $arFields);
            $arItems['results'][] = $result;
        }

        $totalItems = count($result);

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];

    }
    public function getPartnersSellersAction(array $params = [])
    {
        //define("LOG_API_SYNC_ORD_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/ORDController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');
        \Bitrix\Main\Loader::includeModule('iblock');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        // Если startDate передан
        $startDate = isset($queryArray['startDate']) ? date('d.m.Y 00:00:00', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('d.m.Y 23:59:59', strtotime($queryArray['endDate'])) : date('d.m.Y 23:59:59');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос списка Селлеров для Партнера: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        $arSelect = ['ID','NAME','CODE','ACTIVE_DATE','ACTIVE','IBLOCK_ID','IBLOCK_TYPE_ID','DATE_CREATE'];
        $arOrder = ['ID' => 'ASC'];

        $arNavParams = array(
            "nPageSize" => $qty, // количество элементов в выборке
            "iNumPage" => $page,   // номер страницы
        );

        $arFilter = $startDate === null
            ? array(
                "IBLOCK_ID" => 167,
                "ACTIVE_DATE" => "Y",
                "ACTIVE" => "Y",
                "<=DATE_CREATE" => $endDate
            )
            : array(
                "IBLOCK_ID" => 167,
                "ACTIVE_DATE" => "Y",
                "ACTIVE" => "Y",
                "<=DATE_CREATE" => $endDate,
                ">=DATE_CREATE" => $startDate
            );


        $res = \CIBlockElement::GetList($arOrder, $arFilter, false, $arNavParams, $arSelect);

        $result = array();

        // Получение элементов
        $arItems['object'] = (string) "partnersSellerList";

        while($el = $res->GetNextElement())
        {

            $arFields = $el -> GetFields();
            $arProps = $el -> GetProperties();

            $arProp['ASSIGN_LEAD'] = $arProps['ASSIGN_LEAD']['VALUE'];
            $arProp['DATA_DOGOVORA_ZAYMA'] = $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'];
            $arProp['DATA_VYDACHI_DZ'] = $arProps['DATA_VYDACHI_DZ']['VALUE'];
            $arProp['DATE_LEAD'] = $arProps['DATE_LEAD']['VALUE'];
            $arProp['LEAD_ID'] = $arProps['LEAD_ID']['VALUE'];
            $arProp['INN_SELLER'] = $arProps['INN_SELLER']['VALUE'];
            $arProp['REFERRAL_ID'] = $arProps['REFERRAL_ID']['VALUE'];
            $arProp['STATUS'] = $arProps['STATUS']['VALUE'];
            $arProp['DOGOVOR_ZAYMA'] = intval($arProps['DOGOVOR_ZAYMA']['VALUE']);
            $arProp['NOMER_DOGOVORA'] = $arProps['NOMER_DOGOVORA']['VALUE'];
            $arProp['SYNK_S_LID'] = $arProps['SYNK_S_LID']['VALUE'];
            $arProp['SUMMA_PO_DOGOVORU'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']);
            $arProp['SCP_KB_LEAD'] = intval($arProps['SCP_KB_LEAD']['VALUE']);
            $arProp['NEGATIVNAYA_STADIYA_IZ_SMARTA'] = ['id' => $arProps['NEGATIVNAYA_STADIYA_IZ_SMARTA']['VALUE_ENUM_ID'],'value'=>$arProps['NEGATIVNAYA_STADIYA_IZ_SMARTA']['VALUE_ENUM']];
            if ($arProp['SCP_KB_LEAD'] !== 0 && $arProps['DATA_DOGOVORA_ZAYMA']['VALUE'])
            {
                $arProp['SUM_SCP_KB_LEAD'] = intval($arProps['SUMMA_PO_DOGOVORU']['VALUE']) * intval($arProps['SCP_KB_LEAD']['VALUE']) / 100;
            } else
            {
                $arProp['SUM_SCP_KB_LEAD'] = 0;
            }
            $result[] = array_merge($arProp, $arFields);
            $arItems['results'][] = $result;
        }

        $totalItems = count($result);

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];

    }
    public function getRepeatTranshItemsAction(array $params = [])
    {
        define("LOG_API_SYNC_REPEAT_TRANSH_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/repeatTranshController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта SC - Повторный транш: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        // Построение SQL-запросов
        $strCountItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_147 WHERE DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_147 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}')";

        $strItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_147 WHERE DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_147 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountItemsQuery = $DB->query($strCountItemsSQL);
        $totalItems = $resCountItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arItems['object'] = (string) "repeatTransh";
        $resItemsQuery = $DB->query($strItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_57', 147);

        while ($resItem = $resItemsQuery->Fetch()) {
            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = [
                'CONTACT_IDS',
                'CONTACTS',
                'Наблюдатели',
                'Режим расчёта суммы',
                'Сумма налога',
                'СМАРТ ОВК',
                'Для ИП / Юр.Лиц: Банковская выписка за 12 месяцев по всем расчетным и номинальным счетам',
                'Кредитор',
                'Тарифный план займа',
                'Срок займа',
                'Поручитель №1',
                'Поручитель №1 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №1 - Согласие на обработку ПД',
                'Поручитель №2 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №2 - Согласие на обработку ПД',
                'Поручитель №2 - % поручительства',
                'Объект залога',
                'Согласие членов семьи на объект залога',
                'Документы на объект залога',
                'Документы подписаны',
                'Согласие на обработку персональных данных',
                'Для ИП: Цветная копия паспорта (все страницы) / Для Юр.Лиц: Цветные копии паспорта руководителя (представителя) и бенефициара (все страницы)',
                'Для Юр.Лиц: Оборотно-сальдовые ведомости по счетам 66, 67 и 76 с начала года по текущую дату (с расшифровками в разрезе контрагентов)',
                'Для ИП / Юр.Лиц: Бухгалтерская отчетность и налоговая декларация',
                'Для Юр.Лиц: Копия решения о создании юридического лица',
                'Для Юр.Лиц: Копия действующего решения об избрании единоличного исполнительного органа',
                'Для Юр.Лиц: Копия бухгалтерской отчётности',
                'Для Юр.Лиц: Копии имеющихся лицензий',
                'Анкеты: заемщик, представитель, бенефициар',
                'Поручитель №3',
                'Поручитель №4',
                'Поручитель №5',
                'Поручитель №6',
                'Поручитель №3 - % поручительства',
                'Поручитель №4 - % поручительства',
                'Поручитель №5 - % поручительства',
                'Поручитель №6 - % поручительства',
                'Поручитель №3 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №4 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №5 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №6 - Паспорт, СНИЛС / Правоустанавливающие документы для Юр. лиц',
                'Поручитель №3 - Согласие на обработку ПД',
                'Поручитель №4 - Согласие на обработку ПД',
                'Поручитель №5 - Согласие на обработку ПД',
                'Поручитель №6 - Согласие на обработку ПД',
                'Анкета поручителя 3',
                'Анкета поручителя 4',
                'Анкета поручителя 5',
                'Анкета поручителя 6',
                'Комментарий от СЗИ',
                'Комментарий от Андеррайтера',
                'Смарт Аистенок',
                'Смарт вступление в КПК',
                'Вступление в Аистенок (файл)',
                'Вступление в КПК (файл)',
                'Анкета поручителя 1',
                'Анкета поручителя 2',
                'Рабочее время, отказ КК',
                'Рабочее время, отказ клиента',
                'Рабочее время, финансирование предоставлено',
                'Рабочее время, отказ Андеррайтинг',
                'начальная дата метрики на этапе',
                'ИНН',
                'Предварительные условия',
                'Решение клиента по пред.условиям'
            ];
            $res = $this->prepareDataArray($resItem, $fieldsMetadata, $excludedTitles,[],false,false);
            $arItems['results'][] = $res;
        }

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getRegisterRisksItemsAction(array $params = [])
    {
        define("LOG_API_SYNC_REGISTER_RISKS_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/RegisterRisksController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта Реестр Рисков: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        // Построение SQL-запросов
        $strCountItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_176 WHERE CATEGORY_ID = '210' AND DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_176 WHERE CATEGORY_ID = '210' AND UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}'";

        $strItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_176 WHERE CATEGORY_ID = '210' AND DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_176 WHERE CATEGORY_ID = '210' AND UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountItemsQuery = $DB->query($strCountItemsSQL);
        $totalItems = $resCountItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arItems['object'] = (string) "registerRisks";
        $resItemsQuery = $DB->query($strItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_60', 176);

        while ($resItem = $resItemsQuery->Fetch()) {
            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = [
                'Когда передвинут',
                'Кем передвинут',
                'Предыдущая стадия',
                'Описание решения',
                'Документация для тестирования',
                'Ссылка на процесс тестирования',
                'Ошибки при тестировании',
                'Количество ошибок сотрудника',
                'Общее количество ошибок сотрудников',
                'Ошибки системы в текущем месяце',
                'Ошибки системы за всё время',
                'Решённые ошибки системы',
                'Решённые ошибки специалистов',
                'Есть ошибки?',
                'Дата создания документа',
                'Краткое описание проекта(какая у него была цель, какие задачи решал)',
                'Направление',
                'Техническая документация',
                'Бизнес документация',
                'Обучающие материалы',
                'Вероятность, %',
                'NEW сообщение',
                'Текущее сообщение',
                'Этап/колонка',
                'Дата последней корректировки',
                'Название переменной',
                'Канал отправки',
                'Получатель сообщения',
                'Предыдущий канал отправки',
                'Бизнес-Юнит',
                'Воронка ошибки',
                'Характер ошибки',
                'Описание Ошибки',
                'Ссылки на элементы ошибки',
                'Скриншоты и документы',
                'Отчет по устранению (текст)',
                'Отчет по решению (документы и вложения)',
                'Все ошибки (не исп)',
                'БАГ в БП',
                'БАГ специалистов в работе в БП',
                'Всего ошибок найдено',
                'БАГ в БП (кол-во)',
                'БАГ специалистов в работе в БП (кол-во)',
                'Поле буфер',
                'ID ответственногоА (Не исп)',
                'Текущий месяцА (Не исп)',
                'Комментарий',
                'Предложений поступило (кол-во)',
                'Поступившие предложения',
                'Нарушение регламентов специалистами (old)',
                'Нарушения регламентов специалистами (кол-во)',
                'Нарушение регламентов специалистами',
                'Ссылка на элемент ошибки (только регламент)',
                'Ошибка спеца',
                'Ошибка',
                'Сотрудник оспорил ошибку'
            ];
            $res = $this->prepareDataArray($resItem, $fieldsMetadata, $excludedTitles,[],false,false);
            $arItems['results'][] = $res;
        }

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getDealsForSalesAction(array $params = [])
    {
        define("LOG_API_SYNC_DEAL_LOANSELLER_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/DealsLoansSellerController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $uf = isset($queryArray['uf']) ? $queryArray['uf'] : false;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Сделок для ДБ Продажи Займы: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        // Построение SQL-запросов
        $strCountDealsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_deal INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID WHERE b_crm_deal.CATEGORY_ID = '23' AND DATE(b_crm_deal.DATE_MODIFY) <= '{$endDate}' ORDER BY b_crm_deal.ID ASC;"
            : "SELECT COUNT(*) FROM b_crm_deal INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID WHERE b_crm_deal.CATEGORY_ID = '23' AND (b_crm_deal.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}')";

        $strDealSQL = $startDate === null
            ? "SELECT b_crm_deal.*, b_uts_crm_deal.*, GROUP_CONCAT(b_crm_utm.CODE) AS UTM_CODES, GROUP_CONCAT(b_crm_utm.VALUE) AS UTM_VALUES 
               FROM b_crm_deal 
               INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID 
               LEFT JOIN b_crm_utm ON b_crm_deal.ID = b_crm_utm.ENTITY_ID AND b_crm_utm.ENTITY_TYPE_ID = " . \CCrmOwnerType::Deal . " 
               WHERE b_crm_deal.CATEGORY_ID = '23' AND DATE(b_crm_deal.DATE_MODIFY) <= '{$endDate}' 
               GROUP BY b_crm_deal.ID 
               ORDER BY b_crm_deal.ID ASC 
               LIMIT {$qty} OFFSET {$offset};"
            : "SELECT b_crm_deal.*, b_uts_crm_deal.*, GROUP_CONCAT(b_crm_utm.CODE) AS UTM_CODES, GROUP_CONCAT(b_crm_utm.VALUE) AS UTM_VALUES 
               FROM b_crm_deal 
               INNER JOIN b_uts_crm_deal ON b_crm_deal.ID = b_uts_crm_deal.VALUE_ID 
               LEFT JOIN b_crm_utm ON b_crm_deal.ID = b_crm_utm.ENTITY_ID AND b_crm_utm.ENTITY_TYPE_ID = " . \CCrmOwnerType::Deal . " 
               WHERE b_crm_deal.CATEGORY_ID = '23' AND (b_crm_deal.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}') 
               GROUP BY b_crm_deal.ID 
               ORDER BY b_crm_deal.ID ASC 
               LIMIT {$qty} OFFSET {$offset};";
        
        $resCountItemsQuery = $DB->query($strCountDealsSQL);
        $totalItems = $resCountItemsQuery->Fetch()['COUNT(*)'];

        // Выполнение запроса
        $resItemsQuery = $DB->query($strDealSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_DEAL', \CCrmOwnerType::Deal);
        $arItems = [
            'object' => 'deals',  // Указываем объект
            'results' => []       // Инициализируем массив для результатов
        ];

        $dealData = [];

        // Получение элементов
        while ($resItem = $resItemsQuery->Fetch()) {
            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = [];
            $includedTitles = [
                'ID',
                'Название',
                'Тип',
                'Воронка',
                'Стадия сделки',
                'Группа стадии',
                'Новая сделка',
                'Валюта',
                'Сумма',
                'IS_MANUAL_OPPORTUNITY',
                'Компания',
                'Контакт',
                'Контакты',
                'Дата начала',
                'Дата завершения',
                'Доступна для всех',
                'Закрыта',
                'Ответственный',
                'Кем изменена',
                'MOVED_BY_ID',
                'Дата создания',
                'Дата изменения',
                'MOVED_TIME',
                'Источник',
                'Дополнительно об источнике',
                'Лид',
                'Дополнительная информация',
                'Внешний источник',
                'Идентификатор элемента во внешнем источнике',
                'Рекламная система',
                'Тип трафика',
                'Обозначение рекламной кампании',
                'Содержание кампании',
                'Условие поиска кампании',
                'Расторжение договора сбережения',
                'SC - Сопровождение займов Селлеров',
                'Договор кредитной линии',
                'Частичное изъятие по договору сбережения',
                'SC - Повторный транш',
                'ОРД - Маркетинг',
                'SCP - Андеррайтинг',
                'СЗИ',
                'SCP Кредитный Комитет',
                'СМАРТ ОВК',
                'SCP - Выплата Агентского вознаграждения',
                '(КПК) Контрактное финансирование',
                'Кредитный комитет КПК',
                'SC - Финконтроль',
                'Верификация клиентов Seller-Capital',
                'Сопровождение депозитов МКК/КПК',
                'Договор сбережения',
                '(В разработке) Опросы и тестирование',
                'LAST_ACTIVITY_TIME',
                'LAST_ACTIVITY_BY',
                'Причины отказа SCP',
                'utm_content',
                'Тип клиента',
                'Данные о партнере реферале',
                'Новый ЛИД, дата (SC-ЛИДЫ)',
                'Cold box (депозит-юл), дата',
                'Переговоры с ЛПР (депозит-юл), дата',
                'Клиенты с сайта (депозит-юл), дата',
                'Специалист, дата (SC-ЛИДЫ)',
                'КП (депозит-юл), дата',
                'Успешный ЛИД, дата (SC-ЛИДЫ)',
                'Успешный ЛИД, сумм (SC-ЛИДЫ)',
                'Некачественный ЛИД, дата (SC-ЛИДЫ)',
                'Для отчета',
                'Результат Скоринг системы «Seller-Engine»',
                'Предварительно одобренный кредитный лимит / ВКЛ',
                'Сумма депозита',
                'Срок займа / размещения',
                '% Ставка (в зависимости от условия)',
                'Как происходят выплаты % (ежемесячно или в конце срока размещения)',
                'Сбор документов (депозит-юл), дата',
                'Проверка ОВК (депозит-юл), дата',
                'Подписание Договора (депозит-юл), дата',
                'Договор подписан (депозит-юл), дата',
                'Депозит оформлен (депозит-юл), дата',
                'Отказ компании(депозит-юл), дата',
                'Архив-Резерв (депозит-юл), дата',
                'Сбор документов, дата',
                'Сбор документов, сумм',
                'Новый заем, дата',
                'Акция Seller Capital',
                'Переговоры с ЛПР, дата',
                'Переговоры с ЛПР, сумм',
                'Передано на Андеррайтинг, дата',
                'Передано на Андеррайтинг, сумм',
                'Сбор пакета документов, дата',
                'Сбор пакета документов, сумм',
                'Проверка ОВК, дата',
                'Проверка ОВК, сумм',
                'Подписание договора, дата',
                'Подписание договора, сумм',
                'Финансирование, дата',
                'Финансирование, сумм',
                'Финансирование выдано, дата',
                'Финансирование выдано, сумм',
                'Срок ВКЛ / займа (дни) SC',
                'ID копии',
                'Сделка провалена, дата',
                'Сделка провалена, сумм',
                'Отказ СЗИ, дата',
                'Отказ СЗИ, сумм',
                'Отказ Андеррайтинг, дата',
                'Отказ Андеррайтинг, сумм',
                'Отказ клиента (После КК), дата',
                'Отказ клиента (После КК), сумм',
                'Дата посещения',
                'Номер заявки',
                'Дата заявки',
                'Дата принятия решения',
                'Номер договора',
                'Дата договора',
                'Деньги выданы',
                'Сумма займа по договору',
                'Деньги получены',
                'Номер агентского договора',
                'Дата агентского договора',
                'РСП наблюдатель',
                'Номер сделки',
                'Cold Box, сумм (SP-вовлечение)',
                'Звонок №1, сумм (SP-вовлечение)',
                'Звонок №1, дата (SP-вовлечение)',
                'WhatsApp, сумм (SP-вовлечение)',
                'WhatsApp, дата (SP-вовлечение)',
                'E-mail, сумм (SP-вовлечение)',
                'E-mail, дата (SP-вовлечение)',
                'Звонок №2, сумм (SP-вовлечение)',
                'Звонок №2, дата (SP-вовлечение)',
                'Специалист, сумм (SP-вовлечение)',
                'Специалист, дата (SP-вовлечение)',
                'Успешный, сумм (SP-вовлечение)',
                'Успешный, дата (SP-вовлечение)',
                'Верификация, сумм (SP-вовлечение)',
                'Верификация, дата (SP-вовлечение)',
                'Буфер, сумм (SP-вовлечение)',
                'Буфер, дата (SP-вовлечение)',
                'Архив-Резерв, сумм (SP-вовлечение)',
                'Архив-Резерв, дата (SP-вовлечение)',
                'В работе, дата (SP-вовлечение)',
                'В работе, сумм (SP-вовлечение)',
                'Звонок №1, конв. (SP-вовлечение)',
                'WhatsApp, конв. (SP-вовлечение)',
                'E-mail, конв. (SP-вовлечение)',
                'Звонок №2, конв. (SP-вовлечение)',
                'Специалист, конв. (SP-вовлечение)',
                'Дата заявки (Оформление займа Селлерам)',
                'Цель займа подробная (Оформление займа Селлерам)',
                'Дата договора займа(Оформление займа Селлерам)',
                'ID Андеррайтинга',
                'Ежемесячный оборот',
                'SCP-КВ (SCP)',
                'Результат проверки ОВК / СЗИ',
                'Комментарий специалиста ОВК / СЗИ',
                'Вид займа Селлеру (без отсрочки/с отсрочкой)',
                'Тарифный план займа',
                'Срок займа',
                'Сумма к снятию',
                'Кредитор',
                'Поручитель №1',
                'Поручитель №1 - % поручительства',
                'Поручитель №2',
                'Поручитель №2 - % поручительства',
                'Объект залога',
                'Залоговая стоимость',
                'Клиент согласен с предварительными условиями?',
                'SCP - Ревизоры',
                'Ожидаемая дата получения займа',
                'Лимит кредитной линии / ВКЛ',
                'Вид сбережения',
                'Срок сбережения',
                'Подписание Nopaper',
                'Сервис ЭДО для подписания',
                'Нет Инфо, сумм (SP-вовлечение)',
                'Нет Инфо, дата (SP-вовлечение)',
                'Целевой, сумм (SP-вовлечение)',
                'Целевой, дата (SP-вовлечение)',
                'Дата активности (SP-вовлечение)',
                'Дата активности (SC-ЛИДЫ)',
                'Дата активности (SС-Займ)',
                'Стадия отказа (SC)',
                'Окончательное решение КК',
                'Cold Box, дата (SP-вовлечение)',
                'Отказ по сроку, сумм',
                'Отказ по сроку, дата',
                'Повторный скоринг (SC-лиды)',
                'Звонок №1, сумм (SC-лиды)',
                'Целевой ОК, сумм (SC-лиды)',
                'Whatsap, сумм (SC-лиды)',
                'E-mail, сумм (SC-лиды)',
                'Звонок №2, сумм (SC-лиды)',
                'Специалист, сумм (SC-лиды)',
                'Успешный из буфера (SC-лиды)',
                'Конв. звонок №1 (SC-лиды)',
                'Конв. whatsap (SC-лиды)',
                'Конв. e-mail (SC-лиды)',
                'Конв. звонок №2 (SC-лиды)',
                'Конв. специалист (SC-лиды)',
                'Доработка, м',
                'Доработка, сумм',
                'Доработка, дата',
                'Дата последнего успешного звонка',
                'Смарт вступление в Аистенок',
                'Вступление в кооператив (смарт)',
                'Лимиты скоринга',
                'Ссылка/и на сайт (Для баннера) (SCP)',
                'id заявки в орд',
                'Формат посещения офиса',
                'Посмотреть в Тендерплане',
                'ИНН заказчика',
                'ID ответственного (мат.метрики)',
                'Тип сделки',
                'Новый ЛИД, сумм (SC-ЛИДЫ)',
                'Буфер, сумм (SC-ЛИДЫ)',
                'Нет информации о клиенте, сумм (SC-ЛИДЫ)',
                'Некачественный ЛИД, сумм (SC-ЛИДЫ)',
                'Успешный ЛИД, сумм (SC-ЛИДЫ)',
                'SC - Договор займа',
                'Дата начала',
                'Дата события',
                'Лид',
                'Описание события',
                'Предполагаемая дата закрытия',
                'Сделка закрыта',
                'Сумма в валюте учета',
                'Валюта учета',
                'Тип',
                'Тип события',
                'Стадия сделки',
                'Телефон №1',
                'Поручители',
                'COMMENTS',
                'График',
                'Дата',
                'Дата запуска процесса',
                'Дата изменения элемента',
                'Город (old) обязательное',
                'ИНН',
                'ИНН №1',
                'Срок размещения',
                'Собственные средства',
                'NEW ЛИД, дата (лид) удалить',
                'Дата + 1 месяц (не удалять)',
                'Дата смарт "Сопровождение" (не удалять)',
                'Должность',
                'Смарт ОВК',
                'Фамилия',
                'Имя',
                'Отчество',
                'Телефон',
                'E-mail',
                'Город (old)',
                'Тип проверки СЗИ',
                'Результат проверки СЗИ',
                'Рабочее время, новый заем',
                'Рабочее время, исходящий/буферный поток',
                'Рабочее время, переговоры с ЛПР',
                'Рабочее время, сбор документов',
                'Рабочее время, доработка',
                'Рабочее время, передано на Андеррайтинг',
                'Рабочее время, сбор пакета документов',
                'Рабочее время, вступление в Аистенок',
                'Рабочее время, вступление в кооператив',
                'Рабочее время, проверка ОВК',
                'Рабочее время, подписание договора',
                'Рабочее время, финансирование',
                'Рабочее время, передано на сопровождение',
                'Рабочее время, отказ клиента до КК',
                'Рабочее время, отказ по сроку до КК',
                'Рабочее время, отказ по сроку после КК',
                'Рабочее время, отказ СЗИ',
                'Рабочее время, отказ Андеррайтинг',
                'Рабочее время, отказ клиента после КК',
                'Начальная дата метрики',
                'Исходящий/буферный поток, дата',
                'Вступление в Аистенок, дата',
                'Предыдущая стадия',
                'Текущая стадия',
                'Организация Содействие',
                'Запрашиваемая сумма',
                'Плановая дата сделки',
                'Подписант',
                'Комментарий к договору займа',
                'Дата сделки ДКП',
                'Причина отказа',
                'Для ИП / Юр.Лиц: API–ключ',
                'Заявка',
                'Сделка с родственниками',
                'Причина отказа по заявке на заем (подробно)',
                'Адрес залога',
                'Документ ППС',
                'Запись в ЕГРП документа ППС',
                'Запись в ЕГРП, номер договора',
                'Запись в ЕГРП, дата договора',
                'Доля собственности залога',
                'Адрес объекта недвижимости',
                'Кем перенесена на Потенциальный партнер',
                'Скор-балл',
                'Uid Заявки',
                'Филиал',
                'Просрочена'
            ];
            
            // Подготавливаем данные сделки
            $dealData = $this->prepareDataArray($resItem, $fieldsMetadata, $excludedTitles, $includedTitles, $uf, false);
            $dealData['utm_data'] = [];

            // Обработка UTM-меток, если они есть
            if (!empty($resItem['UTM_CODES']) && !empty($resItem['UTM_VALUES'])) {
                $utmCodes = explode(',', $resItem['UTM_CODES']);
                $utmValues = explode(',', $resItem['UTM_VALUES']);

                // Формируем массив UTM-меток
                foreach ($utmCodes as $index => $code) {
                    $dealData['utm_data'][$code] = $utmValues[$index] ?? null;
                }
            }// Добавляем сделку в результаты
            $arItems['results'][] = $dealData;
        }

        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getLeadsAction(array $params = [])
    {
        define("LOG_API_SYNC_LEADS_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/LeadsController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();

        $statusRequest = "Success";
        $taskId = null;
        // Получаем имя текущего контроллера и метода
        $controllerName = get_class($this);
        $methodName = __FUNCTION__;


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;
        $uf = isset($queryArray['uf']) ? $queryArray['uf'] : false;

        $objectData['ITEM_TITLE'] = "Запрос Лиды: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arItems = [];

        // Построение SQL-запросов
        $strCountLeadsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_lead INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID WHERE DATE(b_crm_lead.DATE_MODIFY) <= '{$endDate}' ORDER BY b_crm_lead.ID ASC;"
            : "SELECT COUNT(*) FROM b_crm_lead INNER JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID WHERE (b_crm_lead.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}');";

        $strLeadsSQL = $startDate === null
            ? "SELECT b_crm_lead.*, b_uts_crm_lead.*, GROUP_CONCAT(b_crm_utm.CODE) AS UTM_CODES, GROUP_CONCAT(b_crm_utm.VALUE) AS UTM_VALUES 
               FROM b_crm_lead 
               LEFT JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID 
               LEFT JOIN b_crm_utm ON b_crm_lead.ID = b_crm_utm.ENTITY_ID AND b_crm_utm.ENTITY_TYPE_ID = " . \CCrmOwnerType::Lead . " 
               WHERE DATE(b_crm_lead.DATE_MODIFY) <= '{$endDate}' 
               GROUP BY b_crm_lead.ID 
               ORDER BY b_crm_lead.ID ASC 
               LIMIT {$qty} OFFSET {$offset};"
            : "SELECT b_crm_lead.*, b_uts_crm_lead.*, GROUP_CONCAT(b_crm_utm.CODE) AS UTM_CODES, GROUP_CONCAT(b_crm_utm.VALUE) AS UTM_VALUES 
               FROM b_crm_lead 
               LEFT JOIN b_uts_crm_lead ON b_crm_lead.ID = b_uts_crm_lead.VALUE_ID 
               LEFT JOIN b_crm_utm ON b_crm_lead.ID = b_crm_utm.ENTITY_ID AND b_crm_utm.ENTITY_TYPE_ID = " . \CCrmOwnerType::Lead . " 
               WHERE (b_crm_lead.DATE_MODIFY BETWEEN '{$startDate}' AND '{$endDate}') 
               GROUP BY b_crm_lead.ID 
               ORDER BY b_crm_lead.ID ASC 
               LIMIT {$qty} OFFSET {$offset};";

        $resCountItemsQuery = $DB->query($strCountLeadsSQL);
        $totalItems = $resCountItemsQuery->Fetch()['COUNT(*)'];

        // Выполнение запроса
        $resItemsQuery = $DB->query($strLeadsSQL);

        $fieldsMetadata = $this->getFieldsMetadata('CRM_LEAD', \CCrmOwnerType::Lead);
        $arItems = [
            'object' => 'leads',  // Указываем объект
            'results' => []       // Инициализируем массив для результатов
        ];

        while ($resItem = $resItemsQuery->Fetch()) {
            $leadId = $resItem['ID'];
            // Массив заголовков полей, которые нужно исключить из обработки
            $excludedTitles = ['UTM_CODE','UTM_VALUE'];
            $includedTitles = [
                'ID',
                'Название лида',
                'Обращение',
                'Имя',
                'Отчество',
                'Фамилия',
                'Дата рождения',
                'Название компании',
                'Источник',
                'Стадия',
                'Должность',
                'Город',
                'Район',
                'Область',
                'Страна',
                'Код страны',
                'Валюта',
                'Сумма',
                'Ответственный',
                'Кем создан',
                'Кем изменен',
                'MOVED_BY_ID',
                'Дата создания',
                'Дата изменения',
                'MOVED_TIME',
                'Компания',
                'Контакт',
                'CONTACT_IDS',
                'Дата завершения',
                'SCP - Выплата Агентского вознаграждения',
                'LAST_ACTIVITY_TIME',
                'LAST_ACTIVITY_BY',
                'Статус лида',
                'utm_content',
                'Успешный ЛИД, дата (SC-ЛИДЫ)',
                'Некачественный ЛИД, дата (SC-ЛИДЫ)',
                'Результат Скоринг системы «Seller-Engine»',
                'Предварительно одобренный кредитный лимит / ВКЛ',
                'Смарт Верификация (не удалять)',
                'Причина отказа',
                'Cold Box, сумм (SP-вовлечение)',
                'Cold Box, дата (SP-вовлечение)',
                'Звонок №1, сумм (SP-вовлечение)',
                'Звонок №1, дата (SP-вовлечение)',
                'WhatsApp, сумм (SP-вовлечение)',
                'WhatsApp, дата (SP-вовлечение)',
                'E-mail, сумм (SP-вовлечение)',
                'E-mail, дата (SP-вовлечение)',
                'Звонок №2, сумм (SP-вовлечение)',
                'Звонок №2, дата (SP-вовлечение)',
                'Специалист, сумм (SP-вовлечение)',
                'Специалист, дата (SP-вовлечение)',
                'Успешный, сумм (SP-вовлечение)',
                'Успешный, дата (SP-вовлечение)',
                'Верификация, сумм (SP-вовлечение)',
                'Верификация, дата (SP-вовлечение)',
                'Буфер, сумм (SP-вовлечение)',
                'Буфер, дата (SP-вовлечение)',
                'Архив-Резерв, сумм (SP-вовлечение)',
                'Архив-Резерв, дата (SP-вовлечение)',
                'В работе, дата (SP-вовлечение)',
                'В работе, сумм (SP-вовлечение)',
                'Звонок №1, конв. (SP-вовлечение)',
                'WhatsApp, конв. (SP-вовлечение)',
                'E-mail, конв. (SP-вовлечение)',
                'Звонок №2, конв. (SP-вовлечение)',
                'Специалист, конв. (SP-вовлечение)',
                'Ежемесячный оборот',
                'Нет Инфо, сумм (SP-вовлечение)',
                'Нет Инфо, дата (SP-вовлечение)',
                'Целевой, сумм (SP-вовлечение)',
                'Целевой, дата (SP-вовлечение)',
                'Дата активности (SP-вовлечение)',
                'Дата активности (SC-ЛИДЫ)',
                'Cold Box, сумм (Партнеры привлечение)',
                'Звонок №1, сумм (Партнеры привлечение)',
                'WhatsApp, сумм (Партнеры привлечение)',
                'E-mail, сумм (Партнеры привлечение)',
                'Звонок №2, сумм (Партнеры привлечение)',
                'Специалист, сумм (Партнеры привлечение)',
                'Архив-Резерв, сумм (Партнеры привлечение)',
                'Звонок №1, конв. (Партнеры привлечение)',
                'WhatsApp, конв. (Партнеры привлечение)',
                'E-mail, конв. (Партнеры привлечение)',
                'Звонок №2, конв. (Партнеры привлечение)',
                'Специалист, конв. (Партнеры привлечение)',
                'Сконвертирован, сумм (Партнеры привлечение)',
                'В работе, сумм (Партнеры привлечение)',
                'Реферальная ссылка (Партнеры привлечение - Строка)',
                'ИНН актуальный текст (Партнеры привлечение)',
                'Дата активности (Партнеры привлечение)',
                'Дата активности (SC-партнерка)',
                'Повторный скоринг (SC-лиды)',
                'Звонок №1, сумм (SC-лиды)',
                'Целевой ОК, сумм (SC-лиды)',
                'Whatsap, сумм (SC-лиды)',
                'E-mail, сумм (SC-лиды)',
                'Звонок №2, сумм (SC-лиды)',
                'Специалист, сумм (SC-лиды)',
                'Успешный из буфера (SC-лиды)',
                'Конв. звонок №1 (SC-лиды)',
                'Конв. whatsap (SC-лиды)',
                'Конв. e-mail (SC-лиды)',
                'Конв. звонок №2 (SC-лиды)',
                'Конв. специалист (SC-лиды)',
                'Дата последнего успешного звонка',
                'Лимиты скоринга',
                'Новый ответственный',
                'Ответственный селлер',
                'Новый ЛИД, дата (SC-ЛИДЫ)',
                'Лид от маркетинга',
                'Новый ЛИД, сумм (SC-ЛИДЫ)',
                'Буфер, сумм (SC-ЛИДЫ)',
                'Нет информации о клиенте, сумм (SC-ЛИДЫ)',
                'Некачественный ЛИД, сумм (SC-ЛИДЫ)',
                'Успешный ЛИД, сумм (SC-ЛИДЫ)',
                'Дата начала',
                'Дата события',
                'Лид',
                'Сделка закрыта',
                'Сумма в валюте учета',
                'ID звонка',
                'ID копии',
                'WhatsApp, конв. (SCP)',
                'WhatsApp, конв. (SCP)',
                'WhatsApp, конв. (SCP)',
                'source',
                'utm_content',
                'Вид займа',
                'Вступление в Аистенок',
                'Вступление в кооператив',
                'Деньги выданы',
                'Деньги получены',
                'Документы кадрового учета получены',
                'Доля собственности залога',
                'Как происходят выплаты % (ежемесячно или в конце срока размещения)',
                'Наставник',
                'Одобренная сумма',
                'Ожидаемая сумма выдаваемых займов',
                'Ожидаемое количество сделок в месяц',
                'Отказ Компании, дата (SCP-Подключение)',
                'Отказ Компании, м (SCP-Подключение)',
                'Отказ Компании, сумм (SCP-Подключение)',
                'Отказ Партнера, дата (SCP-Подключение)',
                'Отказ Партнера, м (SCP-Подключение)',
                'Отказ Партнера, сумм (SCP-Подключение)',
                'Отключен после запуска, дата (SCP-Подключение)',
                'Отключен после запуска, м (SCP-Подключение)',
                'Отключен после запуска, сумм (SCP-Подключение)',
                'Повторный контакт, дата (SCP)',
                'Повторный контакт, дата (лид)',
                'Повторный контакт, сумм (SCP)_удалить_ЩАС',
                'Повторный контакт, сумм (лид)',
                'Поручители',
                'Потенциальный ЛИД, дата (лид)',
                'Потенциальный ЛИД, сумм (лид)',
                'Потерян, дата (SCP-Подключение)',
                'Потерян, сумм (SCP-Подключение)',
                'Предыдущая стадия',
                'Скор-балл',
                'Собственные средства',
                'Созаемщики',
                'Срок размещения',
                'Срок с момента начала цикла до передачи в Привлечение (дней)',
                'Срок с момента начала цикла до попадания в Резерв (дней)',
                'Срок с появления карточки до конвертации (дней)',
                'Стадия сделки',
                'Сумма депозита',
                'Текущая стадия',
                'Фамилия',
                'Филиал',
                'Город',
                'Причины отказа SC',
                'Текущий месяц (мат.метрики)'
            ];

            // Подготавливаем данные лида
            $leadData = $this->prepareDataArray($resItem, $fieldsMetadata, $excludedTitles, $includedTitles, $uf, true);
            $leadData['utm_data'] = [];

            // Обработка UTM-меток, если они есть
            if (!empty($resItem['UTM_CODES']) && !empty($resItem['UTM_VALUES'])) {
                $utmCodes = explode(',', $resItem['UTM_CODES']);
                $utmValues = explode(',', $resItem['UTM_VALUES']);

                // Формируем массив UTM-меток
                foreach ($utmCodes as $index => $code) {
                    $leadData['utm_data'][$code] = $utmValues[$index] ?? null;
                }
            }

            // Добавляем лид в результаты
            $arItems['results'][] = $leadData;
        }
        //$totalItems = count($arItems['results']);
        $totalPages = ceil($totalItems / $qty);
        $arItems['total'] = (int) $totalItems;
        $arItems['total_pages'] = (int) $totalPages;
        $arItems['has_more'] = ($offset + $qty < $totalItems);

        $jsonRes = [
            'success' => $arItems,
            'error' => ""
        ];
        // Логируем информацию
        /*\KPLab\API\LogsAction::Request(
            $methodName,                        // Метод запроса (имя метода)
            $url,                               // URL запроса
            $controllerName,                    // имя текущего контроллера
            $request->getRequestMethod(),       // Метод запроса (POST или GET)
            $statusRequest,                     // Статус запроса
            json_encode($jsonRes),              // Ответ на запрос
            $timeData,                          // Время
            $request->getInput(),               // Тело запроса
            json_encode($request->getHeaders()),// Заголовки запроса
            $taskId,                            // Task ID (если есть)
            $point                              // Тип запроса (если есть)
        );*/
        //Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    public function getFincontrolItemsAction(array $params = [])
    {
        define("LOG_API_SYNC_FINCONTROL_CONTROLLER", $_SERVER['DOCUMENT_ROOT']."/local/classes/api/FincontrolController.log");
        $timeData = Logs\TimeData::start();
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $server = $context->getServer();


        \Bitrix\Main\Loader::includeModule('crm');
        \Bitrix\Main\Loader::includeModule('main');

        $point = "EXTRANET_BX";
        $QUERY_STRING = $server['QUERY_STRING'];
        $url = $server['SCRIPT_URI']."?".$QUERY_STRING;

        $REQUEST_TIME = $server['REQUEST_TIME'];
        $objectData['METHOD'] = $server['REQUEST_METHOD'];

        $headers = $request->getHeaders()->toArray();
        $headersValues = array_column($headers, 'values', 'name');
        $recieve = json_decode($request->getInput(), true);

        parse_str($QUERY_STRING, $queryArray);

        // Параметры запроса
        $startDate = isset($queryArray['startDate']) ? date('Y-m-d', strtotime($queryArray['startDate'])) : null;
        $endDate = isset($queryArray['endDate']) ? date('Y-m-d', strtotime($queryArray['endDate'])) : date('Y-m-d');
        $qty = isset($queryArray['qty']) ? (int)$queryArray['qty'] : 50;
        $page = isset($queryArray['page']) ? (int)$queryArray['page'] : 1;
        $offset = ($page - 1) * $qty;

        $objectData['ITEM_TITLE'] = "Запрос Элементов смарта Финконтроль: {$startDate} - {$endDate}";

        // Валидация дат и количества
        if (strtotime($startDate) > strtotime($endDate)) {
            $errorMessage = "400 Bad Request | `endDate` must be greater than `startDate`!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }
        if ($qty > 50) {
            $errorMessage = "400 Bad Request | `qty` must not be greater than 50!";
            $this->addError(new Error($errorMessage, 400));
            $jsonRes = [
                'success' => null,
                'error' => $errorMessage
            ];
            Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
            return null;
        }

        global $DB;
        $arFincontrolItems = [];

        // Построение SQL-запросов
        $strCountUnderwritingItemsSQL = $startDate === null
            ? "SELECT COUNT(*) FROM b_crm_dynamic_items_175 WHERE DATE(UPDATED_TIME) <= '{$endDate}'"
            : "SELECT COUNT(*) FROM b_crm_dynamic_items_175 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}')";

        $strUnderwritingItemsSQL = $startDate === null
            ? "SELECT * FROM b_crm_dynamic_items_175 WHERE DATE(UPDATED_TIME) <= '{$endDate}' ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}"
            : "SELECT * FROM b_crm_dynamic_items_175 WHERE (UPDATED_TIME BETWEEN '{$startDate}' AND '{$endDate}') ORDER BY ID ASC LIMIT {$qty} OFFSET {$offset}";

        $resCountUnderwritingItemsQuery = $DB->query($strCountUnderwritingItemsSQL);
        $totalUnderwritingItems = $resCountUnderwritingItemsQuery->Fetch()['COUNT(*)'];

        // Получение элементов
        $arFincontrolItems['object'] = (string) "fincontrol";
        $resFincontrolItemsQuery = $DB->query($strUnderwritingItemsSQL);
        $fieldsMetadata = $this->getFieldsMetadata('CRM_51', 175);

        while ($resFincontrolItem = $resFincontrolItemsQuery->Fetch()) {
            $excludedTitles = [];
            $res = $this->prepareDataArray($resFincontrolItem, $fieldsMetadata, $excludedTitles,[],false,false);
            $arFincontrolItems['results'][] = $res;
        }

        $totalPages = ceil($totalUnderwritingItems / $qty);
        $arFincontrolItems['total'] = (int) $totalUnderwritingItems;
        $arFincontrolItems['total_pages'] = (int) $totalPages;
        $arFincontrolItems['has_more'] = ($offset + $qty < $totalUnderwritingItems);

        $jsonRes = [
            'success' => $arFincontrolItems,
            'error' => ""
        ];
        Logs\IBlock::setData($url, $recieve, $jsonRes, $objectData, $timeData, $point, $headersValues);
        return $jsonRes['success'];
    }
    //endregion DBActions

    //region private
    private function getUserFieldEnumResult($enumFieldID): ?array
    {
        if ($enumFieldID !== null) {
            $oUserFieldEnum = new \CUserFieldEnum();
            $rsEnum = $oUserFieldEnum::GetList([], ['ID' => $enumFieldID]);
            if ($arEnum = $rsEnum->GetNext()) {
                return [
                    'id' => (string) $arEnum["XML_ID"],
                    'value' => (string) $arEnum["VALUE"]
                ];
            }
        }
        return null;
    }

    private function getStatusResult($enumFieldID, $enumField): ?array
    {
        global $DB;
        if ($enumFieldID !== null) {
            switch ($enumField) {
                case 'SOURCE_ID':
                    $strStatusSQL = "SELECT * FROM b_crm_status WHERE ENTITY_ID='SOURCE' AND STATUS_ID='{$enumFieldID}';";
                    break;
                case 'STAGE_ID':
                    $strStatusSQL = "SELECT * FROM b_crm_status WHERE ENTITY_ID LIKE '%_STAGE%' AND STATUS_ID='{$enumFieldID}';";
                    break;
                case 'STATUS_ID':
                    $strStatusSQL = "SELECT * FROM b_crm_status WHERE ENTITY_ID LIKE 'STATUS' AND STATUS_ID='{$enumFieldID}';";
                    break;

                default:
                    $strStatusSQL = "SELECT * FROM b_crm_status WHERE STATUS_ID='{$enumFieldID}';";
                    break;
            }

            $resStatusQuery = $DB->query($strStatusSQL);
            while ($resStatus = $resStatusQuery->Fetch()) {
                $statusValue = $resStatus["NAME"];
            }

            return [
                "id" => (string) $enumFieldID,
                "value" => (string) $statusValue
            ];
        }
        return null;
    }

    private function getStatusLeadResult($enumFieldID, $enumField): ?array
    {
        global $DB;
        if ($enumFieldID !== null) {
            switch ($enumField) {
                case 'STATUS_ID':
                    $strStatusSQL = "SELECT * FROM b_crm_status WHERE ENTITY_ID LIKE 'STATUS' AND STATUS_ID='{$enumFieldID}';";
                    break;

                default:
                    $strStatusSQL = "SELECT * FROM b_crm_status WHERE STATUS_ID='{$enumFieldID}';";
                    break;
            }

            $resStatusQuery = $DB->query($strStatusSQL);
            while ($resStatus = $resStatusQuery->Fetch()) {
                $statusValue = $resStatus["NAME"];
            }

            return [
                "id" => (string) $enumFieldID,
                "value" => (string) $statusValue
            ];
        }
        return null;
    }

    private function getUser($userItemID): ?array
    {
        global $DB;
        $assignedByUnderwritingItemValue = "";
        $strAssignedByUnderwritingItemSQL = "SELECT * FROM b_user WHERE ID='{$userItemID}';";
        $resAssignedByUnderwritingItemQuery = $DB->query($strAssignedByUnderwritingItemSQL);
        while ($resAssignedByUnderwritingItem = $resAssignedByUnderwritingItemQuery->Fetch()) {
            $assignedByUnderwritingItemValue = $resAssignedByUnderwritingItem["LAST_NAME"] . " " . $resAssignedByUnderwritingItem["NAME"];
        }
        if($assignedByUnderwritingItemValue !== "") {
            return [
                "id" => (string) $userItemID,
                "value" => $assignedByUnderwritingItemValue
            ];
        }
        return null;
    }

    /**
     * Получает метаданные полей, включая пользовательские и стандартные поля.
     *
     * @param string $entityId Идентификатор сущности.
     * @return array Массив метаданных полей.
     */
    private function getFieldsMetadata($objectId, $entityTypeId): array
    {
        $fieldsMetadata = [];

        // Получаем стандартные поля
        $standardFields = $this->getStandardFields($entityTypeId);
        foreach ($standardFields as $field) {
            $fieldsMetadata[$field['FIELD_NAME']] = [
                'originalTitle' => $field['TITLE'],
                'type' => $field['TYPE'],
                'title' => $this->transliterateToLatin($field['TITLE']),
                'other' => $field
            ];
        }

        // Получаем пользовательские поля
        $res = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $objectId]);
        while ($field = $res->Fetch()) {
            // Получаем метку поля из таблицы b_user_field_lang
            $label = $this->getUserFieldLabel($field['ID']);

            // Выполняем транслитерацию на латиницу
            $labelTranslit = $this->transliterateToLatin($label);

            // Транслитерация названия поля для удобства
            $fieldsMetadata[$field['FIELD_NAME']] = [
                'originalTitle' => $label,
                'type' => $field['USER_TYPE_ID'],
                'title' => $labelTranslit ?: $field['FIELD_NAME'] // Используем метку, если она найдена, иначе FIELD_NAME
            ];
        }



        return $fieldsMetadata;
    }

    private function prepareDataArray($item, $fieldsMetadata, $excludedTitles, $includedTitles = [], $uf = false, $isLead = false): array
    {
        $result = [];
        if($uf) {
            foreach ($item as $fieldName => $fieldValue) {
                if($isLead) {
                    if($fieldName == "STATUS_ID") {
                        $fieldName = "STAGE_ID";
                    }
                }

                if (isset($fieldsMetadata[$fieldName])) {
                    $originalTitle = $fieldsMetadata[$fieldName]['originalTitle'];
                    $fieldType = $fieldsMetadata[$fieldName]['type'];
                    $title = $fieldsMetadata[$fieldName]['title'];

                    switch ($fieldType) {
                        case 'string':
                            $result[$fieldName] = isset($fieldValue) ? (string)$fieldValue : null;
                            break;
                        case 'datetime':
                            $result[$fieldName] = isset($fieldValue) ? date('Y-m-d\TH:i:s.msp', strtotime($fieldValue)) : null;
                            break;
                        case 'date':
                            $result[$fieldName] = isset($fieldValue) ? date('Y-m-d\TH:i:s.msp', strtotime($fieldValue)) : null;
                            break;
                        case 'integer':
                            $result[$fieldName] = isset($fieldValue) ? (int)$fieldValue : null;
                            break;
                        case 'double':
                            $result[$fieldName] = isset($fieldValue) ? (float)$fieldValue : null;
                            break;
                        case 'boolean':
                            $result[$fieldName] = isset($fieldValue) ? (bool)$fieldValue : false;
                            break;
                        case 'enumeration':
                            $result[$fieldName] = isset($fieldValue) ? $this->getUserFieldEnumResult($fieldValue) : null;
                            break;
                        case 'crm':
                            $result[$fieldName] = isset($fieldValue) ? $fieldValue : null;
                            break;
                        case 'user':
                            $result[$fieldName] = isset($fieldValue) ? $this->getUser($fieldValue) : null;
                            break;
                        case 'crm_status':
                            if($isLead) {
                                $result[$fieldName] = isset($fieldValue) ? $this->getStatusLeadResult($fieldValue, $fieldName) : null;
                            }
                            else {
                                $result[$fieldName] = isset($fieldValue) ? $this->getStatusResult($fieldValue, $fieldName) : null;
                            }
                            break;
                        case 'employee':
                            $result[$fieldName] = isset($fieldValue) ? $this->getUser($fieldValue) : null;
                            break;
                        case 'crm_category':
                            $result[$fieldName] = isset($fieldValue) ? $this->getCategory($fieldValue) : null;
                            break;
                        case 'file':
                            break;
                        // Добавьте обработку других типов полей, если необходимо
                        default:
                            $result[$fieldName] = isset($fieldValue) ? (string)$fieldValue : null;
                            break;
                    }
                } else {
                    // Если поле стандартное (например, DATE_CREATE), мы можем вручную обработать его
                    if (in_array($fieldName, ['DATE_CREATE', 'DATE_MODIFY'])) {
                        $result[$fieldName] = date('Y-m-d\TH:i:s.msp', strtotime($fieldValue));
                    } else {
                        // Для остальных стандартных полей просто присваиваем значение
                        $result[$fieldName] = $fieldValue;
                    }
                }
            }
        }
        else {
            foreach ($item as $fieldName => $fieldValue) {
                if($isLead) {
                    if($fieldName == "STATUS_ID") {
                        $fieldName = "STAGE_ID";
                    }
                }
                if (isset($fieldsMetadata[$fieldName])) {
                    $originalTitle = $fieldsMetadata[$fieldName]['originalTitle'];
                    $fieldType = $fieldsMetadata[$fieldName]['type'];
                    $title = $fieldsMetadata[$fieldName]['title'];

                    switch ($fieldType) {
                        case 'string':
                            $result[$title] = isset($fieldValue) ? (string) $fieldValue : null;
                            break;
                        case 'datetime':
                            $result[$title] = isset($fieldValue) ? date('Y-m-d\TH:i:s.msp', strtotime($fieldValue)) : null;
                            break;
                        case 'date':
                            $result[$title] = isset($fieldValue) ? date('Y-m-d\TH:i:s.msp', strtotime($fieldValue)) : null;
                            break;
                        case 'integer':
                            $result[$title] = isset($fieldValue) ? (int) $fieldValue : null;
                            break;
                        case 'double':
                            $result[$title] = isset($fieldValue) ? (float) $fieldValue : null;
                            break;
                        case 'boolean':
                            $result[$title] = isset($fieldValue) ? (bool) $fieldValue : false;
                            break;
                        case 'enumeration':
                            $result[$title] = isset($fieldValue) ? $this->getUserFieldEnumResult($fieldValue) : null;
                            break;
                        case 'crm':
                            $result[$title] = isset($fieldValue) ? (int) $fieldValue : null;
                            break;
                        case 'user':
                            $result[$title] = isset($fieldValue) ? $this->getUser($fieldValue) : null;
                            break;
                        case 'crm_status':
                            if($isLead) {
                                $result[$title] = isset($fieldValue) ? $this->getStatusLeadResult($fieldValue,$fieldName) : null;
                            }
                            else {
                                //print_r($fieldsMetadata[$fieldName]);
                                $result[$title] = isset($fieldValue) ? $this->getStatusResult($fieldValue, $fieldName) : null;
                            }
                            break;
                        case 'employee':
                            $result[$title] = isset($fieldValue) ? $this->getUser($fieldValue) : null;
                            break;
                        case 'crm_category':
                            $result[$title] = isset($fieldValue) ? $this->getCategory($fieldValue) : null;
                            break;
                        case 'file':
                            break;
                        // Добавьте обработку других типов полей, если необходимо
                        default:
                            $result[$title] = isset($fieldValue) ? (string) $fieldValue : null;
                            break;
                    }
                } else {
                    // Если поле стандартное (например, DATE_CREATE), мы можем вручную обработать его
                    if (in_array($fieldName, ['DATE_CREATE', 'DATE_MODIFY'])) {
                        $result[$fieldName] = date('Y-m-d\TH:i:s.msp', strtotime($fieldValue));
                    } else {
                        // Для остальных стандартных полей просто присваиваем значение
                        $result[$fieldName] = $fieldValue;
                    }
                }
            }
        }


        return $result;
    }

    /**
     * Выполняет транслитерацию строки на латиницу.
     *
     * @param string $text Текст для транслитерации.
     * @return string Транслитерированный текст.
     */
    private function transliterateToLatin(string $text): string
    {
        // Настройки транслитерации
        $params = array(
            "max_len" => 100,
            "change_case" => 'L', // 'L' для нижнего регистра
            "replace_space" => '_',
            "replace_other" => '_',
            "delete_repeat_replace" => true,
            "use_google" => false,
            "safe_chars" => false
        );

        return $this->toCamelCase(\CUtil::translit($text, "ru", $params));
    }

    /**
     * Преобразует строку из snake_case в camelCase.
     *
     * @param string $string Строка в формате snake_case.
     * @return string Строка в формате camelCase.
     */
    private function toCamelCase(string $string): string
    {
        // Разбиваем строку на части по символу подчеркивания
        $words = explode('_', $string);

        // Приводим первую часть к нижнему регистру, остальные — к верхнему
        $camelCaseString = array_shift($words);
        foreach ($words as $word) {
            $camelCaseString .= ucfirst(strtolower($word));
        }

        return $camelCaseString;
    }

    /**
     * Получает метку пользовательского поля из таблицы b_user_field_lang.
     *
     * @param int $userFieldId Идентификатор пользовательского поля.
     * @return string|null Метка поля или null, если не найдена.
     */
    private function getUserFieldLabel(int $userFieldId): ?string
    {
        global $DB;
        $label = null;

        $res = $DB->Query("SELECT UF.EDIT_FORM_LABEL 
                       FROM b_user_field_lang UF 
                       WHERE UF.USER_FIELD_ID = " . (int)$userFieldId . " AND UF.LANGUAGE_ID = 'ru'");
        if ($fieldLang = $res->Fetch()) {
            $label = $fieldLang['EDIT_FORM_LABEL'];
        }

        return $label;
    }

    /**
     * Получает стандартные поля сущности.
     *
     * @param string $entityTypeId Идентификатор сущности.
     * @return array Массив стандартных полей.
     */
    private function getStandardFields($entityTypeId): array
    {
        $standardFields = [];

        $entity = \Bitrix\Crm\Service\Container::getInstance()->getFactory($entityTypeId);
        if ($entity) {
            $fieldsInfo = $entity->getFieldsInfo();
            foreach ($fieldsInfo as $fieldName => $fieldInfo) {
                $standardFields[] = [
                    'FIELD_NAME' => $fieldName,
                    'TYPE' => $fieldInfo['TYPE'],
                    'TITLE' => $fieldInfo['TITLE'],
                    'OTHER' => $fieldInfo
                ];
            }
        }

        return $standardFields;
    }

    private function getCategory($categoryId): array
    {
        global $DB;
        if($categoryId <> 0) {
            $strCategorySQL = "SELECT * FROM b_crm_deal_category WHERE ID='{$categoryId}';";
            $resCategoryQuery = $DB->query($strCategorySQL);
            while($resCategory = $resCategoryQuery->Fetch()) {
                $categoryValue = $resCategory["NAME"];
            }
        } else {
            $categoryValue = "Займы";
        }
        return [
            "id" => (string) $categoryId,
            "value" => (string) $categoryValue
        ];
    }
    //endregion private
}
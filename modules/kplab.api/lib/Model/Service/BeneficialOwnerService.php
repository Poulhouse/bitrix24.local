<?php

namespace KPLab\API\V2\Model\Service;

use Bitrix\Crm\Service\Container;
use KPLab\Logs\File;

define("LOG_BENEF_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_benef.log");

class BeneficialOwnerService
{
    private int $iblockId;
    private string $priznakCode;
    private array $enumMap = [];
    private ?int $defaultPriznak = null;
    private $factoryCompany;
    private \CIBlockElement $elementApi;

    public function __construct(
        int $iblockId = 170,
        string $priznakCode = 'PRIZNAK'
    ) {
        $this->iblockId       = $iblockId;
        $this->priznakCode    = $priznakCode;
        $this->elementApi     = new \CIBlockElement();
        $this->factoryCompany = Container::getInstance()
            ->getFactory(\CCrmOwnerType::Company);

        $this->initPriznakEnum();
    }

    /**
     * Инициализируем карту XML_ID => ID и default-значение для свойства PRIZNAK
     */
    private function initPriznakEnum(): void
    {
        $propRes = \CIBlockProperty::GetList(
            [],
            [
                'IBLOCK_ID' => $this->iblockId,
                'CODE'      => $this->priznakCode,
            ]
        );
        if ($prop = $propRes->Fetch()) {
            $propId = (int)$prop['ID'];

            $enumRes = \CIBlockPropertyEnum::GetList(
                ['SORT'=>'ASC'],
                ['PROPERTY_ID' => $propId]
            );
            while ($e = $enumRes->Fetch()) {
                $this->enumMap[$e['XML_ID']] = (int)$e['ID'];
            }

            // первое (benef_priznak_1) или просто первый попавшийся
            $this->defaultPriznak =
                $this->enumMap['benef_priznak_1']
                ?? reset($this->enumMap)
                ?? null;
        }
    }

    /**
     * Основной метод — синхронизирует набор бенефициаров для компании
     *
     * @param int $companyId
     * @param array $ownersDetails [ ['guid'=>..., 'proportion'=>..., 'priznak'=>...], ... ]
     */
    public function syncOwners(int $companyId, array $ownersDetails): void
    {
        try {
            // 1) резолвим CRM-ID компаний-бенефициаров и наполняем [$id, $prop, $priznakXml]
            $owners = $this->resolveOwners($companyId, $ownersDetails);

            // 2) собираем существующие элементы ИБ
            $existing = [];
            $rs = \CIBlockElement::GetList(
                ['ID'=>'ASC'],
                [
                    'IBLOCK_ID'       => $this->iblockId,
                    'PROPERTY_CLIENT' => $companyId,
                ],
                false,
                false,
                ['ID','PROPERTY_BENEFICIAR']
            );
            while ($row = $rs->Fetch()) {
                $existing[(int)$row['PROPERTY_BENEFICIAR_VALUE']] = (int)$row['ID'];
            }

            // 3) удаляем те, которых нет в новом списке
            foreach ($existing as $benefId => $elementId) {
                if (!isset(array_flip(array_column($owners, 'id'))[$benefId])) {
                    $this->elementApi->Delete($elementId);
                    unset($existing[$benefId]);
                }
            }

            // 4) создаём или обновляем
            foreach ($owners as $owner) {
                if ($owner['id'] === null) {
                    continue; // пропускаем несуществующих
                }

                $priznakId = $this->enumMap["benef_priznak_".$owner['priznak']]
                    ?? $this->defaultPriznak;

                $props = [
                    'CLIENT'           => $companyId,
                    'BENEFICIAR'       => $owner['id'],
                    'DOLYA_V_KAPITALE' => $owner['prop'],
                    'PRIZNAK'          => $priznakId,
                ];

                if (isset($existing[$owner['id']])) {
                    $this->elementApi->Update(
                        $existing[$owner['id']],
                        ['PROPERTY_VALUES' => $props]
                    );
                } else {
                    $this->elementApi->Add([
                        'IBLOCK_ID'       => $this->iblockId,
                        'NAME'            => "Бенефициар {$owner['id']} для компании {$companyId}",
                        'PROPERTY_VALUES' => $props,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            File::AddMessage(
                $e->getMessage(),
                'Ошибка в BeneficialOwnerService::syncOwners()',
                LOG_BENEF_SERVICE
            );
        }
    }

    /**
     * Вернёт для компании список бенефициаров в виде:
     * [
     *   ['guid'=>string, 'proportion'=>string, 'priznak'=>string],
     *   …
     * ]
     */
    public function loadOwnersDetails(int $companyId): array
    {
        $result    = [];
        $iblockId  = $this->iblockId;
        $el        = new \CIBlockElement();
        $factoryCo = Container::getInstance()->getFactory(\CCrmOwnerType::Company);

        try {
            $rs = \CIBlockElement::GetList(
                ['SORT'=>'ASC'],
                [
                    'IBLOCK_ID'       => $iblockId,
                    'PROPERTY_CLIENT' => $companyId,
                ],
                false,
                false,
                ['ID']
            );

            while ($row = $rs->Fetch()) {
                // Получаем сразу все свойства элемента
                $elData  = $el->GetByID($row['ID'])->GetNext();
                $props   = \CIBlockElement::GetProperty(
                    $iblockId, $row['ID'], ['sort'=>'asc'], []
                );
                $rawProps = [];
                while ($p = $props->Fetch()) {
                    $rawProps[$p['CODE']] = $p['VALUE'];
                }

                $benefId    = (int)$rawProps['BENEFICIAR'];
                $proportion = (string)$rawProps['DOLYA_V_KAPITALE'];
                $priznakId  = (int)$rawProps['PRIZNAK'];

                // Если для безопасника нет ID — пропускаем
                if (!$benefId) {
                    continue;
                }

                // 1) находим GUID исходной карточки
                $item = $factoryCo->getItem($benefId);
                $guid = $item?->get('UF_CRM_COMPANY_SS_AM_ID') ?: null;

                if (!$guid) {
                    // пишем в лог или таймлайн
                    File::AddMessage(
                        "Beneficial ID={$benefId} has no UF_CRM_COMPANY_SS_AM_ID",
                        "loadOwnersDetails()",
                        LOG_BENEF_SERVICE
                    );
                    continue;
                }

                // 2) достаём XML_ID у признака
                $enum = \CIBlockPropertyEnum::GetByID($priznakId);
                $priznakXml = $enum['XML_ID'] ?? array_search(
                    $this->defaultPriznak,
                    $this->enumMap,
                    true
                );

                $result[] = [
                    'guid'       => $guid,
                    'proportion' => $proportion,
                    'priznak'    => (int) str_replace('benef_priznak_', '', $priznakXml),
                ];
            }
        } catch (\Throwable $e) {
            File::AddMessage(
                $e->getMessage(),
                "Ошибка в loadOwnersDetails({$companyId})",
                LOG_BENEF_SERVICE
            );
        }

        return $result;
    }
    /**
     * Для каждого DTO-элемента пытаемся найти Company::ID в CRM
     * и собираем массив:
     *  [
     *    ['id'=>CRM_ID, 'prop'=>доля, 'priznak'=>XML_ID или null],
     *    …
     *  ]
     */
    private function resolveOwners(int $companyId, array $details): array
    {
        $result = [];
        foreach ($details as $d) {
            $items = $this->factoryCompany->getItems([
                'filter'=>['UF_CRM_COMPANY_SS_AM_ID'=>$d['guid']],
                'select'=>['ID'], 'limit'=>1
            ]);
            if (!empty($items)) {
                $crmItem = reset($items);
                $result[] = [
                    'id'      => $crmItem->getId(),
                    'prop'    => $d['proportion'],
                    'priznak' => $d['priznak'] ?? null,
                ];
            } else {
                $result[] = ['id'=>null,'prop'=>null,'priznak'=>null];
                $this->addNotFoundComment($companyId, $d['guid']);
            }
        }
        return $result;
    }

    private function addNotFoundComment(int $companyId, string $guid): void
    {
        $message = "[b]Ошибка при получении данных[/b]\n"
            . "Бенефициар {$guid} не найден в Битрикс. "
            . "Сначала засинхронизируйте его карточку.";
        \CRest::call('crm.timeline.comment.add',[
            'fields'=>[
                'ENTITY_ID'   => $companyId,
                'ENTITY_TYPE' => 'COMPANY',
                'COMMENT'     => $message,
            ]
        ]);
    }

}
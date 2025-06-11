<?php namespace KPLab\API\V2\Model\Service\Sellers;

use Bitrix\Crm\Service\Container;
use Bitrix\Main\ArgumentException;
use KPLab\API\V2\Model\DTO\Sellers\SellerPersonDTO;
use KPLab\API\V2\Model\Service\SellerService;
use KPLab\Logs\File;

define("LOG_SELLER_BENEF_SERVICE", $_SERVER['DOCUMENT_ROOT']."/local/logs/api_services_sellerbenef.log");

class SellerBeneficiarOwnersService
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
    }
    /**
     * Обновляет или создаёт одного бенефициара
     *
     * @param array $beneficiars
     * @param int $sellerCardId
     * @param int|null $crmId
     */
    public function handler(array $beneficiars, int $sellerCardId, ?int $crmId = null): void
    {
        $resolvedOwners = [];
        $seller = new SellerService();

        foreach ($beneficiars as $beneficiarDto) {
            // 1. Ищем или создаём карточку бенефициара
            if ($beneficiarDto instanceof SellerPersonDTO) {
                $beneficiarCompanyId = $seller->syncPerson($beneficiarDto, 'beneficiar', $sellerCardId);
            }

            // 2. Резолвим данные для ИБ: доля, признак и т.п.
            $resolvedOwners[] = $this->resolveOwner($sellerCardId, $beneficiarCompanyId, $beneficiarDto);
        }

        // 3. Синхронизируем список в инфоблок
        $this->syncOwners($sellerCardId, $resolvedOwners);
    }

    private function resolveOwner(int $companyId, int $beneficiarCompanyId, SellerPersonDTO $dto): array
    {
        return [
            'id'      => $beneficiarCompanyId,
            'prop'    => $dto->ownershipShare ?? null, // например: 100.0
            'priznak' => $dto->ownershipSign ?? null,  // например: 'FOUNDER'
        ];
    }

    /**
     * Привязывает компанию-бенефициара к карточке селлера.
     *
     * @param int $sellerCardId ID карточки селлера
     * @param int $beneficiarCompanyId ID карточки бенефициара (COMPANY)
     * @return void
     * @throws ArgumentException
     */
    public function attachBeneficiar(int $sellerCardId, int $beneficiarCompanyId): void
    {
        $factoryCompany = \Bitrix\Crm\Service\Container::getInstance()->getFactory(\CCrmOwnerType::Company);
        $sellerItem = $factoryCompany->getItem($sellerCardId);
        if (!$sellerItem) {
            return;
        }

        $beneficiars = [
            'UF_CRM_1702272911' => $sellerItem->get('UF_CRM_1702272911'),
            'UF_CRM_1702272991' => $sellerItem->get('UF_CRM_1702272991'),
            'UF_CRM_1702273016' => $sellerItem->get('UF_CRM_1702273016'),
            'UF_CRM_1702273043' => $sellerItem->get('UF_CRM_1702273043'),
            'UF_CRM_1702273072' => $sellerItem->get('UF_CRM_1702273072'),
        ];

        $prefixedId = 'CO_' . $beneficiarCompanyId;

        if (!in_array($prefixedId, $beneficiars, true)) {
            foreach ($beneficiars as $field => $value) {
                if (empty($value)) {
                    $sellerItem->set($field, $prefixedId);
                    $sellerItem->set('UF_CRM_UPDATE_INFO_LK', true);
                    break;
                }
            }

            $operation = $factoryCompany->getUpdateOperation($sellerItem);
            $operation->disableAllChecks();
            $operation->launch();
        }
    }

    /**
     * Основной метод — синхронизирует набор бенефициаров для компании
     *
     * @param int $companyId
     * @param array $owners
     */
    public function syncOwners(int $companyId, array $owners): void
    {
        try {
            // 1. Получаем текущих бенефициаров из ИБ
            $existing = [];
            $rs = \CIBlockElement::GetList(
                ['ID' => 'ASC'],
                [
                    'IBLOCK_ID'       => $this->iblockId,
                    'PROPERTY_CLIENT' => $companyId,
                ],
                false,
                false,
                ['ID', 'PROPERTY_BENEFICIAR']
            );

            while ($row = $rs->Fetch()) {
                $benefId = (int)$row['PROPERTY_BENEFICIAR_VALUE'];
                $elementId = (int)$row['ID'];
                $existing[$benefId] = $elementId;
            }

            // 2. Удаляем тех, кого нет в новом списке
            $currentIds = array_column($owners, 'id');
            foreach ($existing as $benefId => $elementId) {
                if (!in_array($benefId, $currentIds, true)) {
                    $this->elementApi->Delete($elementId);
                    unset($existing[$benefId]);
                }
            }

            // 3. Обновляем или добавляем
            foreach ($owners as $owner) {
                if (!$owner['id']) {
                    continue;
                }

                $priznakId = $this->enumMap['benef_priznak_' . $owner['priznak']]
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
                LOG_SELLER_BENEF_SERVICE
            );
        }
    }
}
<?php namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;

/**
 * Конкретная схема для type="UL"
 *
 * @OA\Schema(
 *   schema="DataUL",
 *   allOf={
 *      @OA\Schema(ref="#/components/schemas/LegalEntityData"),
 *     @OA\Schema(
 *       type="object",
 *       required={
 *         "type", "synchId","inn","kpp","companyName","companyRegDate","serviceEDO"
 *       },
 *       @OA\Property(property="synchId",       type="string", example="123456789"),
 *       @OA\Property(property="inn",            type="string", example="7701234567"),
 *       @OA\Property(property="kpp",            type="string", example="770101001"),
 *       @OA\Property(property="companyName",    type="string", example="ООО Ромашка"),
 *       @OA\Property(property="companyFullName",type="string", nullable=true),
 *       @OA\Property(property="companyRegDate", type="string", format="date", example="2010-05-20"),
 *       @OA\Property(property="serviceEDO",     type="string", example="Контур.Эльба"),
 *       @OA\Property(property="fnsDepartment",  type="string", nullable=true),
 *       @OA\Property(property="ogrn",           type="string", nullable=true),
 *       @OA\Property(property="okpo",           type="string", nullable=true),
 *       @OA\Property(property="oktmo",          type="string", nullable=true),
 *       @OA\Property(property="okved",          type="string", nullable=true),
 *       @OA\Property(
 *         property="marketplaceLinks",
 *         type="array",
 *         @OA\Items(type="string")
 *       ),
 *       @OA\Property(
 *         property="address",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Address")
 *       ),
 *
 *          @OA\Property(property="director", ref="#/components/schemas/DataFL"),
 *
 *        @OA\Property(
 *          property="beneficiars",
 *          type="array",
 *          @OA\Items(
 *              oneOf={
 *              @OA\Schema(ref="#/components/schemas/DataFL"),
 *              @OA\Schema(ref="#/components/schemas/DataIP")
 *            },
 *            discriminator={
 *              "propertyName"="type",
 *              "mapping"={
 *                "FL"="#/components/schemas/DataFL",
 *                "IP"="#/components/schemas/DataIP"
 *              }
 *            }
 *          )
 *        )
 *     )
 *   }
 * )
 */
class DataUL extends LegalEntityData
{
    public string             $type;
    public string             $synchId;
    public string             $inn;
    public string             $kpp;
    public string             $companyName;
    public ?string            $companyFullName = null;
    public \DateTimeInterface $companyRegDate;
    public ?string            $fnsDepartment   = null;
    public ?string            $ogrn            = null;
    public ?string            $okpo            = null;
    public ?string            $oktmo           = null;
    public ?string            $okved           = null;
    public array              $marketplaceLinks= [];
    public array              $address         = [];
    public ?DataFL $director = null;
    /** @var DataFL[]|DataIP[] */
    public array $beneficiars = [];

    /**
     * @param array $d
     * @return DataUL
     * @throws ArgumentException
     * @throws \DateMalformedStringException
     */
    public static function init(array $d): self
    {
        foreach (['type','synchId','inn','kpp','companyName','companyRegDate','serviceEDO','director','beneficiars'] as $f) {
            if (empty($d[$f])) {
                throw new ArgumentException("DataUL::init — отсутствует поле {$f}");
            }
        }
        $o = new self();
        $o->type             = $d['type'];
        $o->synchId          = (string)$d['synchId'];
        $o->inn              = (string)$d['inn'];
        $o->kpp              = (string)$d['kpp'];
        $o->companyName      = (string)$d['companyName'];
        $o->companyFullName  = $d['companyFullName'] ?? null;
        $o->companyRegDate   = new \DateTimeImmutable($d['companyRegDate']);
        $o->fnsDepartment    = $d['fnsDepartment'] ?? null;
        $o->ogrn             = $d['ogrn'] ?? null;
        $o->okpo             = $d['okpo'] ?? null;
        $o->oktmo            = $d['oktmo'] ?? null;
        $o->okved            = $d['okved'] ?? null;
        $o->marketplaceLinks = (array)($d['marketplaceLinks'] ?? []);
        $o->address          = (array)($d['address'] ?? []);
        if (!empty($d['director'])) {
            $o->director    = DataFL::init($d['director']);
        }
        $o->beneficiars      = [];
        foreach ((array)($d['beneficiars'] ?? []) as $b) {
            $o->beneficiars[] = PersonData::init($b);
        }
        return $o;
    }
}
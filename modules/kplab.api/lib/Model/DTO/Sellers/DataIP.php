<?php namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;
/**
 * Конкретная схема для type="IP"
 *
 * @OA\Schema(
 *   schema="DataIP",
 *   allOf={
 *      @OA\Schema(ref="#/components/schemas/PersonData"),
 *     @OA\Schema(
 *       type="object",
 *       required={
 *         "type", "synchId","inn","ogrnip","okpo","okved","companyRegDate","serviceEDO"
 *       },
 *       @OA\Property(property="synchId",       type="string", example="1c2c8733-e5d6-41b4-929c-bc196879f785"),
 *       @OA\Property(property="inn",           type="string", example="667100354160"),
 *       @OA\Property(property="ogrnip",        type="string", example="323665800170896"),
 *       @OA\Property(property="okpo",          type="string", example="2025313195"),
 *       @OA\Property(property="okved",         type="string", example="62.01"),
 *       @OA\Property(property="companyRegDate",type="string", format="date-time", example="2023-08-22T00:00:00Z"),
 *       @OA\Property(property="serviceEDO",    type="string", example="Diadoc"),
 *       @OA\Property(property="fnsDepartment",type="string", nullable=true),
 *       @OA\Property(property="firstName",     type="string", nullable=true),
 *       @OA\Property(property="lastName",      type="string", nullable=true),
 *       @OA\Property(property="secondName",    type="string", nullable=true),
 *       @OA\Property(property="birthday",      type="string", format="date-time", nullable=true),
 *       @OA\Property(property="birthPlace",    type="string", nullable=true),
 *       @OA\Property(
 *         property="marketplaceLinks",
 *         type="array",
 *         @OA\Items(type="string", example="https://…")
 *       ),
 *       @OA\Property(
 *         property="address",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Address")
 *       ),
 *       @OA\Property(
 *         property="passport",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Passport")
 *       ),
 *       @OA\Property(property="isManual",      type="boolean", example=false)
 *     )
 *   }
 * )
 */
class DataIP extends PersonData
{
    public string              $type;
    public string              $synchId;
    public string              $inn;
    public string              $ogrnip;
    public string              $okpo;
    public string              $okved;
    public \DateTimeInterface  $companyRegDate;
    public ?string             $fnsDepartment   = null;
    public ?string             $firstName       = null;
    public ?string             $lastName        = null;
    public ?string             $secondName      = null;
    public ?\DateTimeInterface $birthday        = null;
    public ?string             $birthPlace      = null;
    public array               $marketplaceLinks= [];
    public string              $serviceEDO;
    public bool                $isManual;
    public array               $address         = [];
    public array               $passport        = [];

    public function getServiceEDO(): ?string
    {
        return $this->serviceEDO;
    }

    /**
     * @param array $d
     * @return DataIP
     * @throws ArgumentException
     * @throws \DateMalformedStringException
     */
    public static function init(array $d): self
    {
        foreach (['type','synchId','inn','ogrnip','okpo','okved','companyRegDate','serviceEDO'] as $f) {
            if (empty($d[$f])) {
                throw new ArgumentException("DataIP::init — отсутствует поле {$f}");
            }
        }
        $o = new self();
        $o->type            = $d['type'];
        $o->synchId         = (string)$d['synchId'];
        $o->inn             = (string)$d['inn'];
        $o->ogrnip          = (string)$d['ogrnip'];
        $o->okpo            = (string)$d['okpo'];
        $o->okved           = (string)$d['okved'];
        $o->companyRegDate  = new \DateTimeImmutable($d['companyRegDate']);
        $o->fnsDepartment   = $d['fnsDepartment'] ?? null;
        $o->firstName       = $d['firstName'] ?? null;
        $o->lastName        = $d['lastName'] ?? null;
        $o->secondName      = $d['secondName'] ?? null;
        $o->birthday        = isset($d['birthday']) ? new \DateTimeImmutable($d['birthday']) : null;
        $o->birthPlace      = $d['birthPlace'] ?? null;
        $o->marketplaceLinks= (array)($d['marketplaceLinks'] ?? []);
        $o->serviceEDO      = (string)$d['serviceEDO'];
        $o->isManual        = (bool)($d['isManual'] ?? false);
        $o->address         = (array)($d['address'] ?? []);
        $o->passport        = (array)($d['passport'] ?? []);
        return $o;
    }
}
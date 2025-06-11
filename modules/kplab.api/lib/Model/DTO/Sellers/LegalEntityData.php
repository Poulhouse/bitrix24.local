<?php namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;
/**
 * @OA\Schema(
 *   schema="LegalEntityData",
 *   type="object",
 *   required={"type","inn","companyName","companyRegDate","serviceEDO","director"}
 *  )
 */
class LegalEntityData {

    /**
     *   @OA\Property(property="type", type="string", example="UL")
     */
    public string $type;
    /**
     *   @OA\Property(property="synchId", type="string", format="uuid", example="12345678-90ab-cdef-1234-567890abcdef")
     */
    public string $synchId;

    /**
     *   @OA\Property(property="inn", type="string", example="7701234567")
     */
    public string $inn;

    /**
     *   @OA\Property(property="kpp", type="string", nullable=true)
     */
    public string $kpp;

    /**
     *   @OA\Property(property="companyName", type="string", example="ООО Ромашка")
     */
    public string $companyName;

    /**
     *   @OA\Property(property="companyFullName",type="string", nullable=true)
     */
    public ?string $companyFullName = null;

    /**
     *   @OA\Property(property="companyRegDate",type="string", format="date", example="2010-05-20")
     */
    public string $companyRegDate;

    /**
     *   @OA\Property(property="fnsDepartment", type="string", nullable=true)
     */
    public ?string $fnsDepartment = null;

    /**
     *   @OA\Property(property="ogrn", type="string", nullable=true)
     */
    public ?string $ogrn = null;

    /**
     *   @OA\Property(property="okpo", type="string", nullable=true)
     */
    public ?string $okpo = null;

    /**
     *   @OA\Property(property="oktmo", type="string", nullable=true)
     */
    public ?string $oktmo = null;

    /**
     *   @OA\Property(property="okved", type="string", nullable=true)
     */
    public ?string $okved = null;

    /**
     *  @OA\Property(
     *      property="marketplaceLinks",
     *      type="array",
     *      @OA\Items(type="string")
     *  )
     */
    public array $marketplaceLinks = [];

    /**
     *   @OA\Property(property="serviceEDO", type="string", example="Diadoc")
     */
    public string $serviceEDO;

    /**
     * @OA\Property(
     *   property="address",
     *   type="array",
     *   @OA\Items(ref="#/components/schemas/Address")
     * )
     */
    public array $address;

    /**
     * @OA\Property(ref="#/components/schemas/PersonData")
     */
    public PersonData $director;

    /**
     *  @OA\Property(
     *     property="beneficiars",
     *     type="array",
     *     @OA\Items(ref="#/components/schemas/PersonData"),
     *     nullable=true
     *  )
     */
    public ?array $beneficiars = null;

    /**
     *  @OA\Property(
     *     property="bankAccounts",
     *     type="array",
     *     @OA\Items(ref="#/components/schemas/BankAccount"),
     *     nullable=true
     *  )
     */
    public ?array $bankAccounts = null;

    /**
     * @throws ArgumentException|\DateMalformedStringException
     */
    public static function init(array $data): self
    {
        // 1) Проверяем обязательные поля
        $required = ["type","inn","companyName","companyRegDate","serviceEDO","director"];
        foreach ($required as $f) {
            if (!isset($data[$f]) || $data[$f] === '') {
                throw new ArgumentException("Не передано обязательное поле `{$f}` в LegalEntityData");
            }
        }

        // 2) Создаём и заполняем
        $obj = new self();
        $obj->type            = (string)$data['type'];
        $obj->synchId         = (string)$data['synchId'];
        $obj->inn             = (string)$data['inn'];
        $obj->companyName     = (string)$data['companyName'];
        $obj->companyFullName = $data['companyFullName'] ?? null;
        $obj->companyRegDate  = (string)$data['companyRegDate'];
        $obj->serviceEDO      = (string)$data['serviceEDO'];

        // 3) Адреса
        $obj->address = [];
        foreach ($data['address'] ?? [] as $addr) {
            $obj->address[] = Address::init($addr);
        }

        // 4) Директор
        $obj->director = PersonData::init($data['director']);

        // 5) Бенефициары
        $obj->beneficiars = [];
        foreach ($data['beneficiars'] ?? [] as $b) {
            $obj->beneficiars[] = PersonData::init($b);
        }

        // 6) Банковские реквизиты
        $obj->bankAccounts = [];
        foreach ($data['bankAccounts'] ?? [] as $b) {
            $obj->bankAccounts[] = BankAccount::init($b);
        }

        return $obj;
    }
}
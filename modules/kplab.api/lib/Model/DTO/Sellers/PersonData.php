<?php namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;
use KPLab\API\V2\Model\DTO\Sellers\Address;
use KPLab\API\V2\Model\DTO\Sellers\Passport;

/**
 * @OA\Schema(
 *   schema="PersonData",
 *   type="object",
 *   required={"type","inn","firstName","lastName","serviceEDO"}
 * )
 */
class PersonData {


    /**
     *   @OA\Property(property="type", enum={"FL","IP"}, type="string", example="IP")
     */
    public string $type;

    /**
     *   @OA\Property(property="synchId", type="string", format="uuid", example="1c2c8733-e5d6-41b4-929c-bc196879f785")
     */
    public string $synchId;

    /**
     *   @OA\Property(property="inn", type="string", example="667100354160")
     */
    public string $inn;

    /**
     *   @OA\Property(property="phone", type="string", example="+71234567890", nullable=true)
     */
    public ?string $phone = null;

    /**
     *   @OA\Property(property="email", type="string", example="test@test.ru", nullable=true)
     */
    public ?string $email = null;

    /**
     *   @OA\Property(property="firstName", type="string", example="Андрей")
     */
    public string $firstName;

    /**
     *   @OA\Property(property="lastName", type="string", example="Татарченков")
     */
    public string $lastName;

    /**
     *   @OA\Property(property="secondName", type="string", example="Павлович")
     */
    public ?string $secondName = null;

    /**
     *   @OA\Property(property="birthday", type="string", format="date-time", example="1989-03-25T00:00:00Z")
     */
    public string $birthday;

    /**
     *   @OA\Property(property="birthPlace", type="string", nullable=true)
     */
    public ?string $birthPlace = null;

    /**
     *   @OA\Property(property="ogrnip", type="string", example="323665800170896", nullable=true)
     */
    public string $ogrnip;

    /**
     *   @OA\Property(property="okpo", type="string", example="2025313195", nullable=true)
     */
    public string $okpo;

    /**
     *   @OA\Property(property="okved", type="string", example="62.01", nullable=true)
     */
    public string $okved;

    /**
     *   @OA\Property(property="companyRegDate", type="string", format="date-time", example="2023-08-22T00:00:00Z", nullable=true)
     */
    public string $companyRegDate;

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
     * @OA\Property(ref="#/components/schemas/Passport")
     */
    public Passport $passport;

    /**
     *   @OA\Property(property="isManual", type="boolean", example=false)
     */
    public bool $isManual;

    /**
     *  @OA\Property(
     *      property="marketplaceLinks",
     *      type="array",
     *      @OA\Items(type="string")
     *  )
     */
    public array $marketplaceLinks = [];

    /**
     * @OA\Property(
     *   property="bankAccounts",
     *   type="array",
     *   @OA\Items(ref="#/components/schemas/BankAccount")
     * )
     */
    public array $bankAccounts;

    /**
     * @throws ArgumentException|\DateMalformedStringException
     */
    public static function init(array $data): self
    {
        // 1) Проверяем обязательные поля
        $required = ["type","inn","firstName","lastName","serviceEDO","passport"];
        foreach ($required as $f) {
            if (!isset($data[$f]) || $data[$f] === '') {
                throw new ArgumentException("Не передано обязательное поле `{$f}` в PersonData");
            }
        }

        // 2) Создаём и заполняем
        $obj = new self();
        $obj->type = (string)$data['type'];
        $obj->synchId = (string)$data['synchId'];
        $obj->inn = (string)$data['inn'];
        $obj->serviceEDO = (string)$data['serviceEDO'];
        $obj->firstName = $data['firstName'] ?? null;
        $obj->lastName = $data['lastName'] ?? null;
        $obj->secondName = $data['secondName'] ?? null;
        $obj->birthday = $data['birthday'] ?? null;
        $obj->ogrnip = $data['ogrnip'] ?? null;
        $obj->okpo = $data['okpo'] ?? null;
        $obj->okved = $data['okved'] ?? null;
        $obj->companyRegDate = (string)$data['companyRegDate'];
        $obj->isManual = (bool)($data['isManual'] ?? false);
        $obj->marketplaceLinks= $data['marketplaceLinks'] ?? [];

        // Адреса
        $obj->address  = [];
        foreach ($data['address'] ?? [] as $addr) {
            $obj->address[] = Address::init($addr);
        }

        // Паспорт
        $obj->passport = Passport::init($data['passport']);

        // Банковские реквизиты
        $obj->bankAccounts = [];
        foreach ($data['bankAccounts'] ?? [] as $b) {
            $obj->bankAccounts[] = BankAccount::init($b);
        }

        return $obj;
    }
}
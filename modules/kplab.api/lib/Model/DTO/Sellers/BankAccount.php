<?php namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;

/**
 *      @OA\Schema(schema="BankAccount", type="object",
 *         @OA\Property(property="crmId", type="string", nullable=true),
 *         @OA\Property(property="sellerInn", type="string", nullable=true),
 *         @OA\Property(property="title", type="string", example="Расчетный счет"),
 *         @OA\Property(property="nameBank", type="string"),
 *         @OA\Property(property="bankIdCode", type="string"),
 *         @OA\Property(property="checkAccount", type="string"),
 *         @OA\Property(property="adjAccount", type="string"),
 *         @OA\Property(property="isCurrent", type="boolean", example=true),
 *         @OA\Property(property="isNominal", type="boolean", example=false),
 *         @OA\Property(property="isPrimary", type="boolean", example=true)
 *      )
 */
class BankAccount
{
    public ?string $crmId = null;
    public ?string $sellerInn = null;
    public string $title;
    public string $nameBank;
    public string $bankIdCode; //БИК
    public string $checkAccount; //корр.
    public string $adjAccount; //расчетный счет
    public bool $isCurrent; //Расчетный счет
    public bool $isNominal; //Номинальный счет
    public bool $isPrimary; //Основной счет

    /**
     * @throws ArgumentException
     */
    public static function init(array $data): self
    {
        if (empty($data['title'])
            || empty($data['nameBank'])
            || empty($data['bankIdCode'])
            || empty($data['checkAccount'])
            || empty($data['adjAccount'])
            || empty($data['isCurrent'])
            || empty($data['isNominal'])
            || empty($data['isPrimary'])
        ) {
            throw new ArgumentException("Неполные данные BankAccount");
        }
        $ba = new self();
        $ba->title = $data['title'];
        $ba->nameBank     = $data['nameBank'];
        $ba->bankIdCode     = $data['bankIdCode'];
        $ba->checkAccount     = $data['checkAccount'];
        $ba->adjAccount     = $data['adjAccount'];
        $ba->isCurrent     = $data['isCurrent'];
        $ba->isNominal     = $data['isNominal'];
        $ba->isPrimary     = $data['isPrimary'];
        return $ba;
    }
}
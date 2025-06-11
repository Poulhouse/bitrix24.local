<?php namespace KPLab\API\V2\Model\Service\Sellers;

use Bitrix\Main\ArgumentException;
use KPLab\API\V2\Model\DTO\Sellers\BankAccount;

class SellerBankAccountService
{
    /**
     * Обновляет или создаёт один банковский аккаунт
     *
     * @param int         $cardId
     * @param BankAccount $ba
     */
    public function handle(int $cardId, BankAccount $ba): void
    {
        // TODO: логика find-or-create реквизита «Счет»
        // $rqId = $this->findRQ($cardId, 'Банк', ['checkAccount' => $ba->checkAccount]);
        // $this->createOrUpdateRQ($cardId, $rqId, $ba->toArray());
    }
}
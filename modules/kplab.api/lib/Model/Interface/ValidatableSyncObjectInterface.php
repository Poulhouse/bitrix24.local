<?php namespace KPLab\API\V2\Model\Interface;

use InvalidArgumentException;

interface ValidatableSyncObjectInterface
{
    /**
     * Проверяет валидность объекта перед отправкой
     *
     * @throws InvalidArgumentException
     */
    public function validate(): self;

    /**
     * Сборка объекта в массив для отправки
     */
    public function build(): array;
}
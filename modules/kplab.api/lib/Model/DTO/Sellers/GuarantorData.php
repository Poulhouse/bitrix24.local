<?php

namespace KPLab\API\V2\Model\DTO\Sellers;
/**
 * @OA\Schema(
 *   schema="GuarantorData",
 *   type="object",
 *   required={"type","synchId","inn","firstName","lastName","serviceEDO"},
 *    @OA\Property(property="type",       enum={"FL","IP"}, type="string", example="IP"),
 *    @OA\Property(property="synchId",    type="string", format="uuid", example="1c2c8733-e5d6-41b4-929c-bc196879f785"),
 *    @OA\Property(property="inn",        type="string", example="667100354160"),
 *    @OA\Property(property="serviceEDO", type="string", example="Diadoc"),
 *    @OA\Property(property="firstName",  type="string", example="Андрей"),
 *    @OA\Property(property="lastName",   type="string", example="Татарченков"),
 *    @OA\Property(property="secondName",   type="string", example="Павлович"),
 *    @OA\Property(property="birthday",   type="string", format="date-time", example="1989-03-25T00:00:00Z"),
 *    @OA\Property(property="ogrnip",         type="string", example="323665800170896", nullable=true),
 *    @OA\Property(property="okpo",           type="string", example="2025313195",      nullable=true),
 *    @OA\Property(property="okved",          type="string", example="62.01",           nullable=true),
 *    @OA\Property(property="companyRegDate", type="string", format="date-time", example="2023-08-22T00:00:00Z", nullable=true),
 *    @OA\Property(property="phone",           type="string", example="+71234567890", nullable=true),
 *    @OA\Property(property="email",           type="string", example="test@test.ru",  nullable=true),
 *    @OA\Property(
 *      property="address",
 *      type="array",
 *      @OA\Items(ref="#/components/schemas/Address")
 *    ),
 *    @OA\Property(
 *      property="passport",
 *      type="array",
 *      @OA\Items(ref="#/components/schemas/Passport")
 *    ),
 *    @OA\Property(property="isManual",       type="boolean", example=false)
 * )
 */
abstract class GuarantorData
{
    /** @var string */
    public string $type;
}
<?php namespace KPLab\API\V2\Model\DTO\Sellers;

use Bitrix\Main\ArgumentException;

/**
 * Конкретная схема для type="FL"
 *
 * @OA\Schema(
 *   schema="DataFL",
 *   allOf={
 *      @OA\Schema(ref="#/components/schemas/PersonData"),
 *      @OA\Schema(
 *       type="object",
 *       required={
 *         "type", "synchId","inn","firstName","lastName","birthday","serviceEDO"
 *       },
 *       @OA\Property(property="synchId",   type="string", example="1c2c8733-e5d6-41b4-929c-bc196879f785"),
 *       @OA\Property(property="inn",       type="string", example="667100354160"),
 *       @OA\Property(property="firstName", type="string", example="Андрей"),
 *       @OA\Property(property="lastName",  type="string", example="Татарченков"),
 *       @OA\Property(property="birthday",  type="string", format="date-time", example="1989-03-25T00:00:00Z"),
 *       @OA\Property(property="serviceEDO",type="string", example="Diadoc"),
 *       @OA\Property(property="phone",     type="string", example="+71234567890", nullable=true),
 *       @OA\Property(property="email",     type="string", example="test@test.ru", nullable=true),
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
 *       @OA\Property(property="isManual",  type="boolean", example=false)
 *     )
 *   }
 * )
 */
class DataFL extends PersonData
{


    public function getServiceEDO(): ?string
    {
        return $this->serviceEDO;
    }

    /**
     * @param array $d
     * @return DataFL
     * @throws ArgumentException
     * @throws \DateMalformedStringException
     */
    public static function init(array $d): self
    {
        foreach (['type','synchId','inn','firstName','lastName','birthday','serviceEDO'] as $f) {
            if (empty($d[$f])) {
                throw new ArgumentException("DataFL::init — отсутствует поле {$f}");
            }
        }
        $o = new self();
        $o->type       = $d['type'];
        $o->synchId    = (string)$d['synchId'];
        $o->inn        = (string)$d['inn'];
        $o->phone      = $d['phone'] ?? null;
        $o->email      = $d['email'] ?? null;
        $o->firstName  = (string)$d['firstName'];
        $o->lastName   = (string)$d['lastName'];
        $o->secondName = $d['secondName'] ?? null;
        $o->birthday   = new \DateTimeImmutable($d['birthday']);
        $o->birthPlace = $d['birthPlace'] ?? null;
        $o->serviceEDO = (string)$d['serviceEDO'];
        $o->isManual   = (bool)($d['isManual'] ?? false);
        $o->address    = (array)($d['address'] ?? []);
        $o->passport   = (array)($d['passport'] ?? []);
        return $o;
    }
}
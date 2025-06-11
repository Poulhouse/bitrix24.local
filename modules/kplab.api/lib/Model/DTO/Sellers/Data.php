<?php

namespace KPLab\API\V2\Model\DTO\Sellers;

/**
 * @OA\Schema(
 *   schema="Data",
 *   type="object",
 *   required={"type"},
 *   discriminator=@OA\Discriminator(
 *     propertyName="type",
 *     mapping={
 *       "IP"="#/components/schemas/PersonData",
 *       "UL"="#/components/schemas/LegalEntityData"
 *     }
 *   ),
 *   oneOf={
 *     @OA\Schema(ref="#/components/schemas/PersonData"),
 *     @OA\Schema(ref="#/components/schemas/LegalEntityData")
 *   }
 * )
 */
abstract class Data extends BaseData
{
    /** @var string */
    public string $type;
}
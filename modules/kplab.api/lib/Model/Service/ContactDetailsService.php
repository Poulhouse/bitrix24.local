<?php namespace KPLab\API\V2\Model\Service;

class ContactDetailsService
{
    public function load(int $entityId): array
    {
        $contactDetails = [];

        $resFieldMulti = \CCrmFieldMulti::GetListEx(
            [],
            [
                'ENTITY_ID' => \CCrmOwnerType::ResolveName(\CCrmOwnerType::Company),
                'ELEMENT_ID' => $entityId,
                'TYPE_ID' => ['PHONE', 'EMAIL'] // Фильтруем только нужные типы
            ]
        );

        $valueTypeMap = [
            'MOBILE' => 1,
            'WORK' => 2,
            'HOME' => 3
        ];

        $typeIdMap = [
            'PHONE' => 1,
            'EMAIL' => 2
        ];

        while ($multifaceted = $resFieldMulti->fetch()) {
            if (!isset($valueTypeMap[$multifaceted['VALUE_TYPE']]) || !isset($typeIdMap[$multifaceted['TYPE_ID']])) {
                continue;
            }

            $contactDetails[] = [
                'typeId' => $typeIdMap[$multifaceted['TYPE_ID']],
                'valueType' => $valueTypeMap[$multifaceted['VALUE_TYPE']],
                'valueText' => $multifaceted['VALUE'],
                'commentText' => $multifaceted['ENTITY_ID'] . '_' . $multifaceted['ELEMENT_ID']
            ];
        }

        return $contactDetails;
    }
}
<?php

namespace Shekel\ShekelLib\Enums;


use Exception;

class BankResolutionEnum
{

    public const BankResolution1 = 'BankResolutionFormVariant1';
    public const BankResolution2 = 'BankResolutionFormVariant2';
    public const BankResolution3 = 'BankResolutionFormVariant3';
    public const BankResolution4 = 'BankResolutionFormVariant4';
    public const BankResolution5 = 'BankResolutionFormVariant5';
    public const BankResolutionZelle = 'BankResolutionFormVariantZelle';


    /**
     * @throws Exception
     */
    public static function getProperties(string $component, ?string $request_type=null): array
    {
        $type = $request_type ?? $component;
        $properties = match ($type) {
            self::BankResolution1 => [
            ],
            self::BankResolution2 => [
                'branch_code' => 'required|string',
                'swift_code' => 'required|string',
            ],
            self::BankResolution3 => [
                'transit_number' => 'required|string',
                'institution_number' => 'required|string',
                'swift_code' => 'required|string',
            ],
            self::BankResolution4 => [
                'sort_code' => 'required|string',
                'iban' => 'required|string',
            ],
            self::BankResolution5 => [
                'routing_number' => 'required|string',
                'swift_code' => 'required|string',
            ],
            self::BankResolutionZelle => [
                'account_name' => 'required|string',
                'email' => 'required|email',
                'bank_name' => 'string',
                'account_number' => 'string',
            ],
            default => throw new Exception("Workflow not currently handled")
        };
        return array_merge($properties, [
            'bank_type' => 'sometimes|string'
        ]);
    }
}
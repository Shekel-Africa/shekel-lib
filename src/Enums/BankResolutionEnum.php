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
        $data = [
            'bank_name' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required',
        ];
        $type = $request_type ?? $component;
        $properties = match ($type) {
            self::BankResolution1 => array_merge($data, [
            ]),
            self::BankResolution2 => array_merge($data, [
                'branch_code' => 'required|string',
                'swift_code' => 'sometimes|string',
            ]),
            self::BankResolution3 => array_merge($data, [
                'transit_number' => 'required|string',
                'institution_number' => 'required|string',
                'swift_code' => 'required|string',
            ]),
            self::BankResolution4 => array_merge($data,[
                'sort_code' => 'required|string',
                'iban' => 'required|string',
            ]),
            self::BankResolution5 => array_merge($data, [
                'routing_number' => 'required|string',
                'swift_code' => 'sometimes|string',
            ]),
            self::BankResolutionZelle => [
                'account_name' => 'required|string',
                'email' => 'required_without:phone|email',
                'phone' => 'required_without:email|string',
                'bank_name' => 'string',
                'account_number' => 'string',
            ],
            default => throw new Exception("Workflow not currently handled")
        };
        return array_merge($properties, [
            'bank_type' => 'sometimes|string'
        ]);
    }

    public static function preparation($request, array &$data, string $component, ?string $request_type=null): void
    {
        $type = $request_type ?? $component;
        $prepared = match ($type) {
            self::BankResolutionZelle => [
                'bank_name' => 'Zelle',
                'account_number' => $request->email ?? $request->phone,
            ],
            default => []
        };
        $data = array_merge($data, $prepared);
    }
}
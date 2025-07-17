<?php

namespace Shekel\ShekelLib\Enums;

class LendingPartnerFinancingEnum
{
    public const LOCAL = 'local';
    public const INTERNATIONAL = 'international';
    public const LOCAL_INTERNATIONAL = 'local_international';

    public static function getValues(): array
    {
        return [
            self::LOCAL,
            self::INTERNATIONAL,
            self::LOCAL_INTERNATIONAL,
        ];
    }
}
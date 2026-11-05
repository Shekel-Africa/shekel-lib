<?php

namespace Shekel\ShekelLib\Enums;

class InternationalLoanEnum
{
    public const IMPORT = 'import';
    public const PROFIT_SHARE_IMPORT = 'profit share import';
    public const SHIPPING = 'shipping';

    public static function getValues(): array
    {
        return [
            self::IMPORT,
            self::PROFIT_SHARE_IMPORT,
            self::SHIPPING,
        ];
    }

    public static function isInternationalLoan(string $loanType): bool
    {
        return in_array($loanType, self::getValues());
    }
}
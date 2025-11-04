<?php

namespace Shekel\ShekelLib\Enums;
class GateEnum
{
    public const EDIT_ACTION = 'edit-action';

    public const EDIT_ACTION_WITH_COMPANY = 'edit-action-with-company';
    public const ADMIN_ONLY = 'admin-only';
    public const OWNER_ONLY = 'owner-only';
    public const OWNER_ONLY_WITH_COMPANY = 'owner-only-with-company';

    public static function getValues(): array
    {
        return [
            self::EDIT_ACTION,
            self::ADMIN_ONLY,
            self::OWNER_ONLY,
            self::EDIT_ACTION_WITH_COMPANY,
            self::OWNER_ONLY_WITH_COMPANY
        ];
    }
}

<?php

namespace Shekel\ShekelLib\Contracts;

interface AdminActivityLoggerContract
{
    public function log(array $data): void;
}

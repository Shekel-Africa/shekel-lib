<?php

namespace Shekel\ShekelLib\Exceptions;

use Throwable;

class ShekelInvalidPermissionException extends ShekelInvalidArgumentException
{
    public function __construct(string $message = "", int $code = 403, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
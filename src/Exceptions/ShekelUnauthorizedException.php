<?php

namespace Shekel\ShekelLib\Exceptions;

use Throwable;

class ShekelUnauthorizedException extends ShekelInvalidArgumentException
{
    public function __construct(string $message = "", int $code = 401, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
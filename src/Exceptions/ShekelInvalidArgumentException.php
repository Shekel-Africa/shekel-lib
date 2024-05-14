<?php

namespace Shekel\ShekelLib\Exceptions;

use Throwable;

class ShekelInvalidArgumentException extends \InvalidArgumentException
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
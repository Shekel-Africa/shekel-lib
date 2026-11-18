<?php

namespace Shekel\ShekelLib\Exceptions;

use Throwable;

/**
 * A refusal with its HTTP status as the code, rendered by NotFoundHandler as {"message": ...}. A
 * refusal the caller tells apart by reason also carries an error code (e.g. invite_expired),
 * rendered as {"message": ..., "code": ...}; most share a status, so the status alone can't tell
 * them apart.
 */
class ShekelInvalidArgumentException extends \InvalidArgumentException
{
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        private ?string $errorCode = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** Why it was refused, for a caller that tells reasons apart; null when there is none. */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}

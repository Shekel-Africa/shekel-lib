<?php

namespace Shekel\ShekelLib\ServiceAuth\Token;

use RuntimeException;

/**
 * A rejected service token. $status is 401 (not authenticated: bad, missing or unverifiable token,
 * or missing iss/kid/tenant_id) or 403 (authenticated but not allowed). $reason is a short code
 * safe to log; it never contains the token.
 */
class ServiceAuthException extends RuntimeException
{
    public function __construct(private int $status, private string $reason)
    {
        parent::__construct($reason, $status);
    }

    public static function unauthenticated(string $reason): self
    {
        return new self(401, $reason);
    }

    public static function forbidden(string $reason): self
    {
        return new self(403, $reason);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

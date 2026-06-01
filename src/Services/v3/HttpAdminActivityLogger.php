<?php

namespace Shekel\ShekelLib\Services\v3;

use Shekel\ShekelLib\Contracts\AdminActivityLoggerContract;

class HttpAdminActivityLogger implements AdminActivityLoggerContract
{
    public function __construct(private AuthService $authService) {}

    public function log(array $data): void
    {
        $this->authService->logAdminActivity($data);
    }
}

<?php

namespace Shekel\ShekelLib\ServiceAuth\Consumer;

use Illuminate\Http\JsonResponse;

/**
 * EligibilityGuard's answer. On allow, $jws is the compact JWS the decision relied on (store it on
 * the loan as authorization evidence); it is null when servicing was allowed during an outage
 * without a valid projection.
 */
class GuardDecision
{
    public const ALLOW = 'allow';
    public const DENY = 'deny';
    public const FETCH = 'fetch';

    public function __construct(
        public string $outcome,
        public string $state,
        public string $action,
        public string $class,
        public array $reasons = [],
        public ?string $jws = null,
        public bool $fetched = false,
        public bool $signatureApiReachable = true,
    ) {
    }

    public function allowed(): bool
    {
        return $this->outcome === self::ALLOW;
    }

    /**
     * The 409 agreement_required response for a denial.
     */
    public function toResponse(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'agreement_required',
            'message' => 'An agreement must be signed before this action.',
            'action' => $this->action,
            'reasons' => array_values($this->reasons),
        ], 409);
    }
}

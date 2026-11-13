<?php

namespace Shekel\ShekelLib\ServiceAuth\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use LogicException;
use Shekel\ShekelLib\ServiceAuth\CallerPolicy;
use Shekel\ShekelLib\ServiceAuth\ServiceAuthConfig;
use Shekel\ShekelLib\ServiceAuth\Tenant\TenantResolver;
use Shekel\ShekelLib\ServiceAuth\Token\ServiceAuthException;
use Shekel\ShekelLib\ServiceAuth\Token\ServiceTokenClaims;
use Shekel\ShekelLib\ServiceAuth\Token\ServiceTokenVerifier;
use Throwable;

/**
 * Service-to-service authentication with OAuth client_credentials tokens (spec §10.1).
 *
 * Usage (alias service.oauth): the first parameter is the scope the endpoint requires; the
 * services it accepts follow, or come from the route default 'service_callers':
 *
 *     ->middleware('service.oauth:signature-api:obligation.activate,authentication-api')
 *     ->middleware('service.oauth:loan-api:signature.hint')->defaults('service_callers', ['signature-api'])
 *
 * An endpoint that names no accepted service accepts none (403).
 *
 * 401: no/invalid/unverifiable token, alg other than RS256, missing kid/iss/tenant_id, wrong iss,
 *      expired, not a service token.
 * 403: `*` scope, no scope in this service's namespace, missing endpoint scope, caller not in the
 *      caller policy or not accepted by the endpoint, unknown tenant, client_id (route, query or
 *      body) different from the token's tenant_id.
 *
 * On success: selects the tenant through TenantResolver and sets the request attributes
 * service_caller, service_client_id, service_tenant_id and service_scopes. Only the bearer token
 * is read; no other header authenticates.
 */
class OAuthServiceTokenAuthentication
{
    public const ATTR_CALLER = 'service_caller';
    public const ATTR_CLIENT_ID = 'service_client_id';
    public const ATTR_TENANT_ID = 'service_tenant_id';
    public const ATTR_SCOPES = 'service_scopes';
    public const ROUTE_CALLERS = 'service_callers';

    public function __construct(
        private ServiceTokenVerifier $verifier,
        private CallerPolicy $callers,
        private TenantResolver $tenants,
    ) {
    }

    public function handle(Request $request, Closure $next, string $requiredScope = '', string ...$acceptedServices)
    {
        $namespace = ServiceAuthConfig::string('namespace');
        if ($namespace === '' || !str_starts_with($requiredScope, $namespace.':') || strlen($requiredScope) <= strlen($namespace) + 1) {
            // A route that requires another service's scope (or none) is a programming error.
            throw new LogicException("service.oauth needs a required scope in this service's namespace ($namespace).");
        }

        try {
            $claims = $this->verifier->verify((string) $request->bearerToken());
            $caller = $this->authorize($request, $claims, $namespace, $requiredScope, $acceptedServices);
        } catch (ServiceAuthException $e) {
            return $this->deny($request, $e);
        }

        try {
            if (!$this->tenants->isKnown($claims->tenantId)) {
                return $this->deny($request, ServiceAuthException::forbidden('unknown_tenant'), $claims);
            }
            $this->tenants->select($claims->tenantId);
        } catch (Throwable $e) {
            Log::error('Service auth: tenant lookup failed.', ['path' => $request->path(), 'error' => $e->getMessage()]);

            return new JsonResponse(['message' => 'Tenant lookup unavailable.'], 503);
        }

        $request->attributes->set(self::ATTR_CALLER, $caller);
        $request->attributes->set(self::ATTR_CLIENT_ID, $claims->clientId);
        $request->attributes->set(self::ATTR_TENANT_ID, $claims->tenantId);
        $request->attributes->set(self::ATTR_SCOPES, $claims->scopes);

        return $next($request);
    }

    /**
     * @return string the caller's service name
     */
    private function authorize(Request $request, ServiceTokenClaims $claims, string $namespace, string $requiredScope, array $acceptedServices): string
    {
        if (in_array('*', $claims->scopes, true)) {
            throw ServiceAuthException::forbidden('wildcard_scope');
        }
        $inNamespace = array_filter($claims->scopes, fn ($scope) => str_starts_with($scope, $namespace.':'));
        if ($inNamespace === []) {
            throw ServiceAuthException::forbidden('wrong_namespace');
        }
        if (!$claims->hasScope($requiredScope)) {
            throw ServiceAuthException::forbidden('missing_scope');
        }

        $caller = $this->callers->serviceFor($claims->clientId);
        if ($caller === null) {
            throw ServiceAuthException::forbidden('caller_not_in_policy');
        }
        $accepted = $acceptedServices !== [] ? $acceptedServices : (array) ($request->route() ? $request->route()->defaults[self::ROUTE_CALLERS] ?? [] : []);
        if (!in_array($caller, $accepted, true)) {
            throw ServiceAuthException::forbidden('caller_not_accepted');
        }

        foreach ($this->namedClientIds($request) as $clientId) {
            if (!is_string($clientId) || !hash_equals($claims->tenantId, $clientId)) {
                throw ServiceAuthException::forbidden('tenant_mismatch');
            }
        }

        return $caller;
    }

    /**
     * Every client_id the request names: route parameter, query string and body.
     */
    private function namedClientIds(Request $request): array
    {
        $named = [];
        $route = $request->route();
        if ($route && $route->hasParameter('client_id')) {
            $named[] = $route->parameter('client_id');
        }
        if ($request->query->has('client_id')) {
            $named[] = $request->query->all()['client_id'];
        }
        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        if (array_key_exists('client_id', $body)) {
            $named[] = $body['client_id'];
        }

        return $named;
    }

    private function deny(Request $request, ServiceAuthException $e, ?ServiceTokenClaims $claims = null): JsonResponse
    {
        Log::warning('Service auth: request refused.', [
            'status' => $e->status(),
            'reason' => $e->reason(),
            'path' => $request->path(),
            'client_id' => $claims?->clientId,
        ]);

        if ($e->status() === 401) {
            return new JsonResponse(['message' => 'Unauthenticated.'], 401, ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
        }

        return new JsonResponse(['message' => 'Forbidden.'], 403);
    }
}

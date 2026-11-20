<?php

namespace Shekel\ShekelLib\Middleware\v3;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
use Shekel\ShekelLib\ServiceAuth\ServiceAuthConfigurationException;
use Shekel\ShekelLib\Utils\RestrictedClientGuard;
use Shekel\ShekelLib\Utils\ShekelAuth;

class ServiceAuthentication
{

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @param mixed ...$scopes
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$scopes)
    {
        try {
            ShekelAuth::verifyToken($request->bearerToken(), $scopes);
            // Without a scope here or elsewhere on the route, a third-party client's token is refused (S20-4896).
            return RestrictedClientGuard::check($request, $scopes) ?? $next($request);
        } catch(\Throwable $th) {
            if ($th instanceof ServiceAuthConfigurationException) {
                report($th);
                return response()->json(['message' => 'Authentication is not configured'], 500);
            }
            if ($th instanceof ShekelInvalidArgumentException) {
                return response()->json(['message' => $th->getMessage()], $th->getCode());
            }
            if ($th instanceof  \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return response()->json(['message' => $th->getMessage()], $th->getCode() ?? $th->getStatusCode());
            }
            return response()->json(['message' => $th->getMessage()], 400);
        }
    }
}
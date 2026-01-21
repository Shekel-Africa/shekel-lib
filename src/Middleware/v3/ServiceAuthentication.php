<?php

namespace Shekel\ShekelLib\Middleware\v3;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
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
            return $next($request);
        } catch(\Throwable $th) {
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
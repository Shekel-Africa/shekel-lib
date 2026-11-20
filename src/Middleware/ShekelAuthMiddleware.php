<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Services\AuthService;
use Illuminate\Support\Facades\Auth;
use Shekel\ShekelLib\Utils\RestrictedClientGuard;
use Shekel\ShekelLib\Utils\ShekelAuth;

class ShekelAuthMiddleware
{

    public function __construct(
        private AuthService $authService
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @param string|null $guard
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, ?string $guard=null)
    {
        try {
            $this->authService->setToken($request->bearerToken());
            ShekelAuth::setAuthToken($request->bearerToken());
            ShekelAuth::setAuthXToken($request->header('x-token'));
            $userCheck = $this->authService->getAuthenticated(['excludeImage'=>true], $guard);
            if (!$userCheck->successful()) {
                return response()->json($userCheck->json(), $userCheck->status());
            }
            $user = $userCheck->json('data');
            Auth::guard()->setUser(new GenericUser($user));
            // authentication-api accepted this token; a third-party client's token still needs a
            // route that declares a scope (S20-4896).
            return RestrictedClientGuard::check($request) ?? $next($request);
        } catch(\Throwable $th) {
            if ($th instanceof  \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return response()->json(['message' => $th->getMessage()], $th->getStatusCode());
            }
            return response()->json(['message' => $th->getMessage()], 400);

        }
    }
}

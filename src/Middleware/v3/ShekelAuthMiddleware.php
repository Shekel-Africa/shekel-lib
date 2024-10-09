<?php

namespace Shekel\ShekelLib\Middleware\v3;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
use Shekel\ShekelLib\Services\v3\AuthService;
use Illuminate\Support\Facades\Auth;
use Shekel\ShekelLib\Utils\ShekelAuth;

class ShekelAuthMiddleware
{
    public function __construct(private AuthService $authService) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @param mixed ...$guards
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }
        try {
            $this->authService->setToken($request->bearerToken());
            ShekelAuth::setAuthToken($request->bearerToken());
            ShekelAuth::setAuthXToken($request->header('x-token'));

            $userCheck = null;
            foreach ($guards as $guard) {
                $userCheck = $this->authService->getAuthenticated(['excludeImage'=>true], $guard);
                if ($userCheck->successful()) {
                    $user = $userCheck->json('data');
                    Auth::guard()->setUser(new GenericUser($user));
                    return $next($request);
                }
            }

            return response()->json($userCheck->json(), $userCheck->status());
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

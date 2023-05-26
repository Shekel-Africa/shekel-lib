<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;
use Shekel\ShekelLib\Services\AuthService;
use Illuminate\Support\Facades\Auth;

class ShekelAuthMiddleware
{

    private $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $this->authService->setToken($request->bearerToken());
            $userCheck = $this->authService->getAuthenticated(['excludeImage'=>true]);
            if (!$userCheck->successful()) {
                return response()->json($userCheck->json(), $userCheck->status());
            }
            $user = $userCheck->json('data');
            Auth::guard()->setUser(new GenericUser($user));
            return $next($request);
        } catch(\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], $th->getStatusCode());
        }
    }
}

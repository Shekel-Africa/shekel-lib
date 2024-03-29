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
     * @param string|null $guard
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $guard=null)
    {
        try {
            $this->authService->setToken($request->bearerToken());
            $userCheck = $this->authService->getAuthenticated(['excludeImage'=>true], $guard);
            if (!$userCheck->successful()) {
                return response()->json($userCheck->json(), $userCheck->status());
            }
            $user = $userCheck->json('data');
            Auth::guard()->setUser(new GenericUser($user));
            return $next($request);
        } catch(\Throwable $th) {
            if ($th instanceof  \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return response()->json(['message' => $th->getMessage()], $th->getStatusCode());
            }
            return response()->json(['message' => $th->getMessage()], 400);

        }
    }
}

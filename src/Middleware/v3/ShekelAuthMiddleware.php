<?php

namespace Shekel\ShekelLib\Middleware\v3;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Services\v3\AuthService;
use Illuminate\Support\Facades\Auth;

class ShekelAuthMiddleware
{
    public function __construct(private AuthService $authService) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @param string|null $guard
     * @return Response|RedirectResponse
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

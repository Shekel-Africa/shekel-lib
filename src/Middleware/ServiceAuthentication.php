<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;
use Shekel\ShekelLib\Services\AuthService;
use Illuminate\Support\Facades\Auth;

class ServiceAuthentication
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
    public function handle(Request $request, Closure $next, ...$scopes)
    {
        try {
            $this->authService->setToken($request->bearerToken());
            $this->authService->verifyToken($scopes);
            $user = auth()->user();
            if (empty($user)) {
                return response()->json(['message' => 'User not set'], 400);
            }
            //kyc not completed
            if (!empty($scopes) && !$user->kyc_complete) {
                return response()->json(['message' => 'Kyc not completed'], 403);
            }
            return $next($request);
        } catch(\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], $th->getStatusCode());
        }
    }
}

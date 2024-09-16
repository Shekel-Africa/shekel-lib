<?php

namespace Shekel\ShekelLib\Middleware\v3;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Services\v3\AuthService;
use Illuminate\Support\Facades\Auth;
use Shekel\ShekelLib\Utils\ShekelAuth;

class ServiceAuthentication
{

    private $authService;

    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$scopes)
    {
        try {
            $this->authService->setToken($request->bearerToken());
            ShekelAuth::setAuthToken($request->bearerToken());
            ShekelAuth::setXToken($request->header('x-token'));
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
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
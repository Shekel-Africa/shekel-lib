<?php

namespace Shekel\ShekelLib\Middleware\v3;

use Closure;
use Illuminate\Http\Request;
use Shekel\ShekelLib\Services\v3\AuthService;

class KycVerifiedMiddleware
{
    public function __construct() {
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next) {
        $user = auth()->user();
        if (empty($user)) {
            return response()->json(['message' => 'User not set'], 400);
        }
        //kyc not completed
        if (!$user->kyc_complete) {
            return response()->json(['message' => 'Kyc not completed'], 403);
        }
        return $next($request);
    }
}
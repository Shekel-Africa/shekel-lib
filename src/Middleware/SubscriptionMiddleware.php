<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Shekel\ShekelLib\Services\TransactionService;
use Shekel\ShekelLib\Services\AuthService;

class SubscriptionMiddleware
{
    private $transactionService;
    private $authService;
    public function __construct(AuthService $authService, TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        try {
            $token = $request->bearerToken();
            $this->transactionService->setToken($token);
            $user = auth()->user();
            
            if($user->user_type == 'admin'){
                $this->authService->setToken($token);
                $this->authService->verifyToken($permissions);
                return $next($request);
            }

            $subscription = $this->transactionService->getActiveSubscription();
            if (!$subscription->successful()) {
                return response()->json($subscription->json(), $subscription->status());
            }
            $activeSub = $subscription->json('data');
            if (empty($activeSub)) {
                return response()->json(["message" => "No Active Subscription"], 402);
            }

            return $next($request);
        } catch(\Throwable $th) {
            if ($th instanceof  \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return response()->json(['message' => $th->getMessage()], $th->getStatusCode());
            }
            return response()->json(['message' => $th->getMessage()], 400);

        }
    }
}
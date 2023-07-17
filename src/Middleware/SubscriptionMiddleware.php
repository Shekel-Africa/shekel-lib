<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Shekel\ShekelLib\Services\TransactionService;

class SubscriptionMiddleware
{
    private $transactionService;
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $this->transactionService->setToken($request->bearerToken());
            $user = auth()->user();
            if($user->user_type != 'admin') {
                $subscription = $this->transactionService->getActiveSubscription();
                if (!$subscription->successful()) {
                    return response()->json($subscription->json(), $subscription->status());
                }
                $activeSub = $subscription->json('data');
                if (empty($activeSub)) {
                    return response()->json(["message" => "No Active Subscription"], 402);
                }
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

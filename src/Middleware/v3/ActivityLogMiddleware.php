<?php

namespace Shekel\ShekelLib\Middleware\v3;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Jenssegers\Agent\Agent;
use Shekel\ShekelLib\Models\ActivityLog;
use Shekel\ShekelLib\Utils\PassportToken;

class ActivityLogMiddleware
{
    private Agent $agent;
    public function __construct(private ActivityLog $activityLog) {
        $this->agent = new Agent();
    }


    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response): void
    {
        if (empty($request->bearerToken())) {
            return;
        }
        $user_agent = $request->header('User-Agent');
        $respStatus = 0;
        try {
            $respStatus = $response->status();
        } catch (\Throwable $th) {
        }
        $data = [
            'url' => $request->fullUrl(),
            'headers' => json_encode($response->headers->all()),
            'ip' => $request->header('X-Forwarded-For') ?? $request->ip(),
            'properties' => json_encode($request->all()),
            'response_data' => json_encode($response),
            'status' => $respStatus,
            'initiator_id' => PassportToken::getUserFromToken($request->bearerToken())['user_id'],
            'user_agent' => $user_agent,
            'device' => json_encode([
                'browser' => $this->agent->browser($user_agent),
                'device' => $this->agent->device($user_agent),
                'platform' => $this->agent->platform($user_agent)
            ])
        ];

        if ($request->hasHeader('x-token')) {
            $data['actor_id'] = PassportToken::getUserFromToken($request->header('x-token'))['user_id'];
        }

        try {
            $client = PassportToken::getClientDetailFromRequest($request);
            $data['app_client_id'] = $client['client_id'];
        } catch (\Throwable $th) {

        }
        $this->activityLog->create($data);
    }
}

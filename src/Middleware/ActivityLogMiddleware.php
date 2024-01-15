<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shekel\ShekelLib\Models\ActivityLog;
use Shekel\ShekelLib\Utils\PassportToken;
use Jenssegers\Agent\Agent;

class ActivityLogMiddleware
{
    private $activityLog;
    public function __construct(ActivityLog $activityLog) {
        $this->activityLog = $activityLog;
        $this->agent = new Agent();
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
        return $next($request);
    }

    public function terminate($request, $response)
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
            $data['client_id'] = $client['client_id'];
        } catch (\Throwable $th) {

        }
        $this->activityLog->create($data);
    }
}

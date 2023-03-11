<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shekel\ShekelLib\Models\ActivityLog;

class ActivityLogMiddleware
{
    private $activityLog;
    public function __construct(ActivityLog $activityLog) {
        $this->activityLog = $activityLog;
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
        logger('Token', [$request->bearerToken()]);
        logger("Request", [json_encode($request)]);
        logger("Request IP", [json_encode($request->ip())]);
        logger("Headers", [json_encode($response->headers->all())]);
        logger("Response", [json_encode($response)]);
    }
}

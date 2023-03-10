<?php

namespace Shekel\ShekelLib\Middleware;

use Illuminate\Http\Request;
use Shekel\ShekelLib\Models\ActivityLog;

class ActivityLogMiddleware
{
    private $activityLog;
    public function __construct(ActivityLog $activityLog) {
        $this->activityLog = $activityLog;
    }



    public function terminate($request, $response)
    {
//        dump($request);
//        dump($response);
        logger("Request", $request);
        logger("Response", $response);
    }
}

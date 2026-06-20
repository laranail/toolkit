<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Http\Middleware;

use Closure;
use Simtabi\Laranail\Toolkit\Models\AccessLog;

class AccessLogMiddleware
{
    public function handle($request, Closure $next)
    {
        $logData = [
            'ip' => $request->ip() ? $request->ip() : null,
            'method' => $request->method() ? $request->method() : null,
            'url' => $request->fullUrl() ? $request->fullUrl() : null,
            'user_agent' => $request->header('User-Agent') ? $request->header('User-Agent') : null,
            'request_data' => $request->all() ? json_encode($request->all()) : null,
        ];

        AccessLog::create($logData);

        return $next($request);
    }
}

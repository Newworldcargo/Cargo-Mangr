<?php

namespace Modules\CustomerPortalApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $header = config('customerportalapi.request_id_header', 'X-Request-ID');
        $requestId = trim((string) $request->header($header));

        if ($requestId === '' || strlen($requestId) > 128) {
            $requestId = (string) Str::uuid();
        }

        $request->attributes->set('portal_request_id', $requestId);
        $response = $next($request);
        $response->headers->set($header, $requestId);

        return $response;
    }
}

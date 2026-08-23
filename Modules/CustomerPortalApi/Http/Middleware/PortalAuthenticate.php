<?php

namespace Modules\CustomerPortalApi\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('web')->check()) {
            return new JsonResponse([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'A valid customer session is required.',
                    'retryable' => false,
                ],
                'requestId' => $request->attributes->get('portal_request_id'),
            ], 401, [
                'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
            ]);
        }

        return $next($request);
    }
}

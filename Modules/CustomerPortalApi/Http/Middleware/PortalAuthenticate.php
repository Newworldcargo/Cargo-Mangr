<?php

namespace Modules\CustomerPortalApi\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\CustomerPortalApi\Services\Portal\CustomerContext;

class PortalAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('web')->check()) {
            return $this->problem($request, 'UNAUTHENTICATED', 'A valid customer session is required.', 401);
        }

        if (!app(CustomerContext::class)->client()) {
            return $this->problem($request, 'FORBIDDEN', 'This account is not enabled for the customer portal.', 403);
        }

        return $next($request);
    }

    private function problem(Request $request, $code, $message, $status)
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'retryable' => false,
            ],
            'requestId' => $request->attributes->get('portal_request_id'),
        ], $status, [
            'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
        ]);
    }
}

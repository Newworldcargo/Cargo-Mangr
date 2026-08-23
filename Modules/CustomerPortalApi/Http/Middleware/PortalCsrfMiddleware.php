<?php

namespace Modules\CustomerPortalApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;
use Modules\CustomerPortalApi\Services\Portal\PortalBffService;

class PortalCsrfMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $bff = app(PortalBffService::class);
        $unsafe = in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($bff->isBffRequest($request)) {
            if ($unsafe && !$bff->validBffCsrf($request)) {
                return $this->problem($request, 'CSRF_TOKEN_MISMATCH', 'A valid CSRF token is required.', 419);
            }
            return $next($request);
        }

        if (!$request->session()->token()) {
            $request->session()->regenerateToken();
        }

        $token = $request->session()->token();
        $unsafe = in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $exempt = $request->is('api/v1/auth/login') || $request->is('api/v1/auth/register') || $request->is('api/v1/auth/verify');

        if ($unsafe && !$exempt) {
            $provided = (string) $request->header(config('customerportalapi.csrf_header', 'X-CSRF-Token'));

            if ($provided === '' || !hash_equals($token, $provided)) {
                return $this->problem($request, 'CSRF_TOKEN_MISMATCH', 'A valid CSRF token is required.', 419);
            }
        }

        $response = $next($request);
        $response->headers->setCookie(Cookie::make(
            config('customerportalapi.csrf_cookie', 'nwc_csrf'),
            $token,
            120,
            '/',
            config('session.domain'),
            (bool) config('customerportalapi.cookie_secure', false),
            false,
            false,
            config('customerportalapi.cookie_same_site', 'lax')
        ));

        return $response;
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
        ], $status, ['X-Request-ID' => $request->attributes->get('portal_request_id')]);
    }
}

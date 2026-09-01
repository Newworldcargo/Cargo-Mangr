<?php

namespace Modules\CustomerPortalApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PortalContractMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $unsafe = in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $contentType = strtolower((string) $request->header('Content-Type'));
        $hasBody = $request->getContent() !== '' || $request->all() !== [];
        $isPortalFileContentUpload = $request->is('api/v1/files/*/content');

        if ($unsafe && $hasBody && !$isPortalFileContentUpload && strpos($contentType, 'application/json') === false && $request->allFiles() === []) {
            return $this->problem($request, 'UNSUPPORTED_MEDIA_TYPE', 'Requests must use application/json.', 415);
        }

        return $next($request);
    }

    private function problem(Request $request, $code, $message, $status)
    {
        $response = new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'requestId' => $request->attributes->get('portal_request_id'),
        ], $status);

        if ($request->attributes->has('portal_request_id')) {
            $response->headers->set('X-Request-ID', $request->attributes->get('portal_request_id'));
        }

        return $response;
    }
}

<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalResponse
{
    public static function success(Request $request, $data, $status = 200, array $meta = [])
    {
        $payload = [
            'data' => $data,
            'requestId' => self::requestId($request),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return new JsonResponse($payload, $status, [
            'X-Request-ID' => self::requestId($request),
        ]);
    }

    public static function problem(Request $request, $code, $message, $status, array $fieldErrors = [], $retryable = false)
    {
        $error = [
            'code' => $code,
            'message' => $message,
            'retryable' => (bool) $retryable,
        ];

        if ($fieldErrors !== []) {
            $error['fieldErrors'] = $fieldErrors;
        }

        return new JsonResponse([
            'error' => $error,
            'requestId' => self::requestId($request),
        ], $status, [
            'X-Request-ID' => self::requestId($request),
        ]);
    }

    private static function requestId(Request $request)
    {
        return (string) ($request->attributes->get('portal_request_id') ?: $request->header('X-Request-ID', ''));
    }
}

<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\CustomerPortalApi\Models\PortalIdempotencyRecord;

class IdempotencyService
{
    public function key(Request $request)
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        return $key !== '' ? $key : null;
    }

    public function fingerprint(Request $request)
    {
        return hash('sha256', $request->getMethod() . '|' . $request->getPathInfo() . '|' . json_encode($request->all()));
    }

    public function find($clientId, $operation, $key, $fingerprint)
    {
        if (!$key) return null;
        $record = PortalIdempotencyRecord::where('client_id', $clientId)
            ->where('operation', $operation)
            ->where('idempotency_key', $key)
            ->where('expires_at', '>', now())
            ->first();
        if (!$record) return null;
        if (!hash_equals($record->fingerprint, $fingerprint)) {
            abort(409, 'IDEMPOTENCY_KEY_REUSED');
        }
        return response()->json($record->response_body, $record->response_status ?: 200);
    }

    public function remember($clientId, $operation, $key, $fingerprint, $response)
    {
        if (!$key) return $response;
        PortalIdempotencyRecord::updateOrCreate(
            ['client_id' => $clientId, 'operation' => $operation, 'idempotency_key' => $key],
            [
                'fingerprint' => $fingerprint,
                'response_status' => $response->getStatusCode(),
                'response_body' => json_decode($response->getContent(), true),
                'expires_at' => now()->addHours(24),
            ]
        );
        return $response;
    }
}

<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;
use Modules\CustomerPortalApi\Services\LipilaGateway;
use Modules\CustomerPortalApi\Services\LipilaPayments;

class LipilaWebhookController extends Controller
{
    public function __invoke(Request $request, LipilaGateway $gateway, LipilaPayments $payments)
    {
        abort_unless($gateway->verifyWebhook($request), 401);
        $reference = $request->input('referenceId', $request->input('data.referenceId'));
        if (!is_string($reference)) return response()->json(['received' => false], 422);
        $intent = PortalPaymentIntent::where('provider', 'lipila')->where('intent_id', $reference)->first();
        if ($intent) {
            $eventHash = hash('sha256', (string) $request->header('webhook-id'));
            $payloadHash = hash('sha256', $request->getContent());
            \Illuminate\Support\Facades\DB::table('payment_webhook_events')->insertOrIgnore([
                'provider' => 'lipila', 'event_hash' => $eventHash, 'payload_hash' => $payloadHash,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $event = \Illuminate\Support\Facades\DB::table('payment_webhook_events')->where('provider', 'lipila')->where('event_hash', $eventHash)->first();
            if (!hash_equals($event->payload_hash, $payloadHash)) return response()->json(['received' => false], 409);
            if ($event->processed_at) return response()->json(['received' => true]);
            try {
                // Callbacks are notifications, never authority for the amount or paid state.
                $result = $payments->refresh($intent, true);
                if (!in_array($result->status, ['succeeded', 'failed', 'review'], true)) return response()->json(['received' => false], 503);
                \Illuminate\Support\Facades\DB::table('payment_webhook_events')->where('id', $event->id)->update(['processed_at' => now(), 'updated_at' => now()]);
            } catch (\Throwable $e) {
                return response()->json(['received' => false], 503);
            }
        }
        return response()->json(['received' => true]);
    }
}

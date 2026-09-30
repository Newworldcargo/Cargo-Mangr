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
            try {
                // Callbacks are notifications, never authority for the amount or paid state.
                $payments->refresh($intent);
            } catch (\Throwable $e) {
                return response()->json(['received' => false], 503);
            }
        }
        return response()->json(['received' => true]);
    }
}

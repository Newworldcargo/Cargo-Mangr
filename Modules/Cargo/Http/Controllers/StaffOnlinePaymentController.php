<?php

namespace Modules\Cargo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Services\ShipmentOperationAccessService;
use Modules\Cargo\Services\StaffOnlinePaymentBill;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;
use Modules\CustomerPortalApi\Http\Resources\PortalPaymentIntentResource;
use Modules\CustomerPortalApi\Services\LipilaGateway;
use Modules\CustomerPortalApi\Services\LipilaPayments;
use Modules\CustomerPortalApi\Services\PaymentAttemptGuard;

class StaffOnlinePaymentController extends Controller
{
    private function authorizePayment(Request $request, int $id): Shipment
    {
        $shipment = Shipment::findOrFail($id);
        abort_unless(app(ShipmentOperationAccessService::class)->canOperate($request->user(), $shipment, 'confirm-shipment-payment'), 403);
        return $shipment;
    }

    public function store(Request $request, int $shipment)
    {
        $model = $this->authorizePayment($request, $shipment);
        if (!app(LipilaGateway::class)->ready()) return response()->json(['message' => 'Online payments are not available yet. Please use offline payment.'], 503);
        $input = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[+0-9 ()-]{8,30}$/D'],
            'idempotencyKey' => ['required', 'uuid'],
            'final_total' => ['required', 'numeric', 'min:0.01'],
            'discount_type' => ['nullable', 'in:fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'charges' => ['nullable', 'array', 'max:30'],
            'charges.*.description' => ['required', 'string', 'max:255'],
            'charges.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $phone = preg_replace('/[^0-9]/', '', $input['phone']);
        if (preg_match('/^0[79][0-9]{8}$/', $phone)) $phone = '260' . substr($phone, 1);
        if (!preg_match('/^260[79][0-9]{8}$/', $phone)) return response()->json(['message' => 'Enter a Zambia mobile-money number.'], 422);
        $invoice = app(StaffOnlinePaymentBill::class)->prepare($model->id, $request->user(), $input);
        $intent = app(LipilaPayments::class)->create($invoice, (int) $model->client_id, [
            'method' => 'mobile-money', 'phone' => $phone, 'idempotencyKey' => $input['idempotencyKey'],
        ], $request->user()->id);
        return response()->json(['data' => (new PortalPaymentIntentResource($intent))->resolve($request)], 201);
    }

    public function status(Request $request, int $shipment)
    {
        $model = $this->authorizePayment($request, $shipment);
        $intent = app(PaymentAttemptGuard::class)->unresolved($shipment)
            ?: PortalPaymentIntent::where('shipment_id', $shipment)->latest('id')->first();
        $freshStatus = true;
        if ($intent && $intent->provider === 'lipila' && app(LipilaGateway::class)->configured()) {
            try { $intent = app(LipilaPayments::class)->refresh($intent); }
            catch (\Throwable $e) {
                $freshStatus = false;
                Log::warning('Staff online payment status unavailable.', ['intent_id' => $intent->intent_id, 'exception' => get_class($e)]);
            }
        }
        $paid = (bool) $model->fresh()->paid;
        $bill = \App\Models\Transxn::where('shipment_id', $shipment)->where('status', '!=', 'voided_duplicate')->latest('id')->first();
        return response()->json([
            'data' => $intent ? (new PortalPaymentIntentResource($intent))->resolve($request) : null,
            'paid' => $paid, 'available' => app(LipilaGateway::class)->ready(), 'statusAvailable' => $freshStatus,
            'bill' => $bill ? ['total' => $bill->total, 'currency' => $bill->currency] : null,
            'canPrompt' => !$paid && (!$intent || $intent->status === 'failed') && app(LipilaGateway::class)->ready(),
        ]);
    }
}

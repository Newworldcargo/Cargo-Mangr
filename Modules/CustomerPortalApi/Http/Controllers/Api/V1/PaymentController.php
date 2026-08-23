<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Transxn;
use Modules\CustomerPortalApi\Http\Resources\PortalPaymentIntentResource;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;

class PaymentController extends PortalController
{
    public function createIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoiceId' => ['required', 'integer'],
            'method' => ['required', 'in:mobile-money,card'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'A valid invoice and payment method are required.', 422, $validator->errors()->toArray());

        $client = $this->customerContext->requireClient();
        $invoice = Transxn::whereKey($request->input('invoiceId'))
            ->whereHas('shipment', function ($query) use ($client) { $query->where('client_id', $client->id); })
            ->first();
        if (!$invoice) return $this->problem($request, 'NOT_FOUND', 'Invoice not found.', 404);
        if (in_array($invoice->status, ['completed', 'refund_requested', 'partially_refunded'], true)) {
            return $this->problem($request, 'INVOICE_NOT_PAYABLE', 'This invoice is already settled.', 422);
        }

        if (!config('customerportalapi.payment_provider')) {
            return $this->problem($request, 'PAYMENT_PROVIDER_NOT_CONFIGURED', 'Payment processing is not enabled in this environment.', 503, [], true);
        }

        $amountMinor = max(0, (int) round(((float) $invoice->total) * 100));
        $intent = PortalPaymentIntent::create([
            'intent_id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'method' => $request->input('method'),
            'status' => 'requires_action',
            'currency' => 'USD',
            'amount_minor' => $amountMinor,
            'revision' => 1,
        ]);
        return $this->success($request, (new PortalPaymentIntentResource($intent))->resolve($request), 201);
    }

    public function showIntent(Request $request, $intent)
    {
        $model = PortalPaymentIntent::where('intent_id', $intent)
            ->where('client_id', $this->customerContext->requireClient()->id)
            ->first();
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Payment intent not found.', 404);
        return $this->success($request, (new PortalPaymentIntentResource($model))->resolve($request));
    }
}

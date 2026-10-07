<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Modules\CustomerPortalApi\Models\PortalWallet;
use Modules\CustomerPortalApi\Models\PortalWalletLedger;
use Modules\CustomerPortalApi\Services\MobilePaymentGateway;
use App\Models\Transxn;
use Modules\CustomerPortalApi\Http\Resources\PortalPaymentIntentResource;
use Modules\CustomerPortalApi\Models\PortalPaymentIntent;

class PaymentController extends PortalController
{
    public function createIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoiceId' => ['required', 'integer'],
            'method' => ['required', 'in:mobile-money,wallet'],
            'phone' => ['required_if:method,mobile-money', 'nullable', 'regex:/^\\+[1-9][0-9]{7,14}$/'],
            'provider' => ['required_if:method,mobile-money', 'nullable', 'in:MTN,Airtel,Zamtel'],
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

        $existingIntent = PortalPaymentIntent::where('client_id', $client->id)
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', ['requires_action', 'processing', 'pending', 'succeeded', 'confirmed', 'completed'])
            ->latest('id')
            ->first();
        if ($existingIntent) {
            return $this->success($request, (new PortalPaymentIntentResource($existingIntent))->resolve($request));
        }

        if ($request->input('method') === 'wallet') return $this->payWithWallet($request, $invoice, $client);

        $provider = trim((string) config('customerportalapi.payment_provider', ''));
        if (!app(MobilePaymentGateway::class)->enabled()) {
            return $this->problem($request, 'PAYMENT_PROVIDER_NOT_CONFIGURED', 'Payment processing is not enabled in this environment.', 503, [], true);
        }

        $amountMinor = max(0, (int) round(((float) $invoice->total) * 100));
        if ($amountMinor < 1) {
            return $this->problem($request, 'INVOICE_NOT_PAYABLE', 'This invoice does not have an outstanding amount.', 422);
        }
        $currency = strtoupper((string) ($invoice->currency ?: 'USD'));
        $created = false;
        $intent = DB::transaction(function () use ($client, $invoice, $request, $currency, $amountMinor, &$created) {
            Transxn::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $existing = PortalPaymentIntent::where('client_id', $client->id)->where('invoice_id', $invoice->id)
                ->whereIn('status', ['requires_action', 'processing', 'pending', 'succeeded', 'confirmed', 'completed'])->latest('id')->first();
            if ($existing) return $existing;
            $created = true;
            return PortalPaymentIntent::create([
                'intent_id' => (string) Str::uuid(), 'client_id' => $client->id, 'invoice_id' => $invoice->id,
                'method' => $request->input('method'), 'status' => 'processing', 'currency' => $currency,
                'amount_minor' => $amountMinor, 'revision' => 1,
            ]);
        });
        if (!$created) return $this->success($request, (new PortalPaymentIntentResource($intent))->resolve($request));
        try {
            $providerPayload = app(MobilePaymentGateway::class)->create([
            'intentId' => $intent->intent_id,
            'invoiceId' => (string) $invoice->id,
            'invoiceNumber' => (string) $invoice->receipt_number,
            'shipmentId' => (string) optional($invoice->shipment)->id,
            'shipmentCode' => (string) optional($invoice->shipment)->code,
            'customerId' => (string) $client->id,
            'method' => $request->input('method'),
            'currency' => $currency,
            'amountMinor' => $amountMinor,
            'phone' => $request->input('phone'),
            'mobileMoneyProvider' => $request->input('provider'),
        ]);
            $intent->status = $providerPayload['status'] ?? 'processing';
            $intent->provider_reference = $providerPayload['providerReference'] ?? null;
            $intent->client_token = $providerPayload['clientToken'] ?? null;
            $intent->save();
        } catch (\Throwable $e) { report($e); }
        if (in_array($intent->status, ['succeeded', 'confirmed', 'completed'], true)) {
            $invoice->status = 'completed';
            $invoice->save();
        }
        return $this->success($request, (new PortalPaymentIntentResource($intent))->resolve($request), 201);
    }

    public function showIntent(Request $request, $intent)
    {
        $model = PortalPaymentIntent::where('intent_id', $intent)
            ->where('client_id', $this->customerContext->requireClient()->id)
            ->first();
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Payment intent not found.', 404);
        if (in_array($model->status, ['requires_action', 'processing', 'pending'], true)) {
            try {
                $payload = app(MobilePaymentGateway::class)->status($model->intent_id, $model->provider_reference);
                if ($payload) {
                    DB::transaction(function () use ($model, $payload) {
                        $locked = PortalPaymentIntent::whereKey($model->id)->lockForUpdate()->firstOrFail();
                        if (!in_array($locked->status, ['requires_action', 'processing', 'pending'], true)) return;
                        $locked->status = $payload['status'] ?? $locked->status;
                        $locked->save();
                        if (app(MobilePaymentGateway::class)->successful($locked->status)) {
                            Transxn::whereKey($locked->invoice_id)->update(['status' => 'completed']);
                        }
                    });
                    $model = $model->fresh();
                }
            } catch (\Throwable $e) { report($e); }
        }
        return $this->success($request, (new PortalPaymentIntentResource($model))->resolve($request));
    }

    private function payWithWallet(Request $request, $invoice, $client)
    {
        $wallet = PortalWallet::where('client_id', $client->id)->first();
        if (!$wallet) return $this->problem($request, 'INSUFFICIENT_BALANCE', 'Add wallet balance before paying.', 422);
        return DB::transaction(function () use ($request, $invoice, $client, $wallet) {
            $invoice = Transxn::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $existing = PortalPaymentIntent::where('client_id', $client->id)->where('invoice_id', $invoice->id)->whereIn('status', ['requires_action', 'processing', 'pending'])->latest('id')->first();
            if ($existing) return $this->success($request, (new PortalPaymentIntentResource($existing))->resolve($request));
            $wallet = PortalWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status === 'completed') return $this->problem($request, 'INVOICE_NOT_PAYABLE', 'This invoice is already settled.', 422);
            $currency = strtoupper((string) ($invoice->currency ?: 'USD'));
            if ($currency !== strtoupper($wallet->currency)) return $this->problem($request, 'CURRENCY_MISMATCH', 'Use mobile money for this invoice currency.', 422);
            $amount = (int) round(((float) $invoice->total) * 100);
            $balance = (int) PortalWalletLedger::where('wallet_id', $wallet->id)->where('bucket', 'available')->where('status', 'posted')->sum('amount_minor');
            if ($amount < 1 || $balance < $amount) return $this->problem($request, 'INSUFFICIENT_BALANCE', 'Add wallet balance before paying.', 422);
            $intent = PortalPaymentIntent::create([
                'intent_id' => (string) Str::uuid(), 'client_id' => $client->id, 'invoice_id' => $invoice->id,
                'method' => 'wallet', 'status' => 'succeeded', 'currency' => $currency, 'amount_minor' => $amount, 'revision' => 1,
            ]);
            PortalWalletLedger::create(['wallet_id' => $wallet->id, 'amount_minor' => -$amount, 'bucket' => 'available',
                'type' => 'payment', 'status' => 'posted', 'reference_type' => 'invoice', 'reference_id' => $invoice->id]);
            $wallet->revision++; $wallet->save();
            $invoice->status = 'completed'; $invoice->save();
            return $this->success($request, (new PortalPaymentIntentResource($intent))->resolve($request), 201);
        });
    }
}

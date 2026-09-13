<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $provider = trim((string) config('customerportalapi.payment_provider', ''));
        if ($provider === '') {
            return $this->problem($request, 'PAYMENT_PROVIDER_NOT_CONFIGURED', 'Payment processing is not enabled in this environment.', 503, [], true);
        }

        $amountMinor = max(0, (int) round(((float) $invoice->total) * 100));
        $currency = strtoupper((string) ($invoice->currency ?: 'USD'));
        $providerPayload = $this->createProviderIntent($provider, [
            'intentId' => (string) Str::uuid(),
            'invoiceId' => (string) $invoice->id,
            'invoiceNumber' => (string) $invoice->receipt_number,
            'shipmentId' => (string) optional($invoice->shipment)->id,
            'shipmentCode' => (string) optional($invoice->shipment)->code,
            'customerId' => (string) $client->id,
            'method' => $request->input('method'),
            'currency' => $currency,
            'amountMinor' => $amountMinor,
        ]);

        $intent = PortalPaymentIntent::create([
            'intent_id' => $providerPayload['intentId'],
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'method' => $request->input('method'),
            'status' => $providerPayload['status'],
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'provider_reference' => $providerPayload['providerReference'],
            'client_token' => $providerPayload['clientToken'],
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

    private function createProviderIntent(string $provider, array $payload): array
    {
        $defaults = [
            'intentId' => $payload['intentId'],
            'status' => 'requires_action',
            'providerReference' => null,
            'clientToken' => null,
        ];

        $webhookUrl = trim((string) config('customerportalapi.payment_webhook_url', ''));
        if ($webhookUrl === '') {
            return $defaults;
        }

        $request = Http::timeout(15)->acceptJson();
        $token = trim((string) config('customerportalapi.payment_webhook_token', ''));
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post($webhookUrl, array_merge($payload, ['provider' => $provider]));
            if (!$response->successful()) {
                Log::warning('Customer portal payment provider returned a non-success response.', [
                    'provider' => $provider,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return $defaults;
            }

            $data = $response->json();
            return [
                'intentId' => (string) ($data['intentId'] ?? $payload['intentId']),
                'status' => (string) ($data['status'] ?? 'requires_action'),
                'providerReference' => isset($data['providerReference']) ? (string) $data['providerReference'] : null,
                'clientToken' => isset($data['clientToken']) ? (string) $data['clientToken'] : null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Customer portal payment provider could not create an intent.', [
                'provider' => $provider,
                'error' => $exception->getMessage(),
            ]);
            return $defaults;
        }
    }
}

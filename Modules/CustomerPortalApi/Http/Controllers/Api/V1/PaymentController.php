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
use Modules\CustomerPortalApi\Services\LipilaGateway;
use Modules\CustomerPortalApi\Services\LipilaPayments;

class PaymentController extends PortalController
{
    public function createIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoiceId' => ['required', 'integer'],
            'method' => ['required', 'in:mobile-money,card'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'A valid invoice and payment method are required.', 422, $validator->errors()->toArray());
        if (config('customerportalapi.payment_provider') !== 'lipila' && !(config('customerportalapi.payment_provider') === 'local-uat' && app()->environment(['local', 'testing']))) {
            return $this->problem($request, 'PAYMENTS_UNAVAILABLE', 'Online payments are currently unavailable. Please contact your branch.', 503);
        }

        $client = $this->customerContext->requireClient();
        $invoice = Transxn::whereKey($request->input('invoiceId'))
            ->whereHas('shipment', function ($query) use ($client) { $query->where('client_id', $client->id); })
            ->first();
        if (!$invoice) return $this->problem($request, 'NOT_FOUND', 'Invoice not found.', 404);
        if (config('customerportalapi.payment_provider') === 'lipila') {
            if (!app(LipilaGateway::class)->ready()) {
                return $this->problem($request, 'PAYMENTS_UNAVAILABLE', 'Online payments are currently unavailable. Please contact your branch.', 503);
            }
            if (!is_string($request->input('phone')) || !preg_match('/^[+0-9 ()-]{8,30}$/D', $request->input('phone'))) {
                return $this->problem($request, 'VALIDATION_FAILED', 'Please enter a valid payment phone number.', 422);
            }
            $phone = preg_replace('/[^0-9]/', '', $request->input('phone'));
            if (preg_match('/^0[79][0-9]{8}$/', $phone)) $phone = '260' . substr($phone, 1);
            $input = $request->all();
            $input['phone'] = $phone;
            $input['idempotencyKey'] = $request->header('Idempotency-Key', $request->input('idempotencyKey'));
            $rules = ['phone' => ['required', 'regex:/^260[79][0-9]{8}$/'], 'idempotencyKey' => ['nullable', 'uuid']];
            if ($request->input('method') === 'card') {
                $rules['phone'] = ['required', 'regex:/^[1-9][0-9]{7,14}$/'];
                foreach (['firstName', 'lastName', 'city', 'address', 'zip'] as $field) $rules['billing.' . $field] = ['required', 'string', 'max:150'];
                $rules['billing.email'] = ['required', 'email', 'max:150'];
                $rules['billing.country'] = ['required', 'regex:/^[A-Z]{2}$/'];
            }
            $validation = Validator::make($input, $rules);
            if ($validation->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Please check your payment details.', 422, $validation->errors()->toArray());
            try {
                $intent = app(LipilaPayments::class)->create($invoice, (int) $client->id, array_merge($validation->validated(), ['method' => $request->input('method')]));
                return $this->success($request, (new PortalPaymentIntentResource($intent))->resolve($request), 201);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return $this->problem($request, 'INVOICE_NOT_PAYABLE', collect($e->errors())->flatten()->first(), 422, $e->errors());
            }
        }
        if (in_array($invoice->status, Transxn::settledStatuses(), true)) {
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

        $provider = trim((string) config('customerportalapi.payment_provider', ''));
        if ($provider === '') {
            return $this->problem($request, 'PAYMENT_PROVIDER_NOT_CONFIGURED', 'Payment processing is not enabled in this environment.', 503, [], true);
        }

        $amountMinor = max(0, (int) round(((float) $invoice->total) * 100));
        if ($amountMinor < 1) {
            return $this->problem($request, 'INVOICE_NOT_PAYABLE', 'This invoice does not have an outstanding amount.', 422);
        }
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
        if (!Transxn::whereKey($model->invoice_id)->when($model->shipment_id, fn ($query) => $query->where('shipment_id', $model->shipment_id))
            ->whereHas('shipment', function ($query) use ($model) { $query->where('client_id', $model->client_id); })->exists()) {
            return $this->problem($request, 'NOT_FOUND', 'Payment intent not found.', 404);
        }
        if ($model->provider === 'lipila' && app(LipilaGateway::class)->configured()) {
            try {
                $model = app(LipilaPayments::class)->refresh($model);
            } catch (\Throwable $e) {
                Log::warning('Lipila status check deferred.', ['intent_id' => $model->intent_id, 'exception' => get_class($e)]);
            }
        }
        return $this->success($request, (new PortalPaymentIntentResource($model))->resolve($request));
    }

    public function latestIntent(Request $request, $invoice)
    {
        $model = PortalPaymentIntent::where('invoice_id', $invoice)
            ->where('client_id', $this->customerContext->requireClient()->id)->latest('id')->first();
        if (!$model) return $this->success($request, null);
        return $this->showIntent($request, $model->intent_id);
    }

    private function createProviderIntent(string $provider, array $payload): array
    {
        $defaults = [
            'intentId' => $payload['intentId'],
            'status' => 'requires_action',
            'providerReference' => null,
            'clientToken' => null,
        ];

        if ($provider === 'local-uat' && app()->environment(['local', 'testing'])) {
            return [
                'intentId' => $payload['intentId'],
                'status' => 'succeeded',
                'providerReference' => 'LOCAL-UAT-' . strtoupper(substr(str_replace('-', '', $payload['intentId']), 0, 12)),
                'clientToken' => null,
            ];
        }

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

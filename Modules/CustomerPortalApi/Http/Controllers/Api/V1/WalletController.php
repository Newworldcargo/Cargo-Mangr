<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\CustomerPortalApi\Services\MobilePaymentGateway;
use Modules\CustomerPortalApi\Http\Resources\WalletResource;
use Modules\CustomerPortalApi\Models\PortalWallet;
use Modules\CustomerPortalApi\Models\PortalWalletLedger;

class WalletController extends PortalController
{
    public function show(Request $request)
    {
        $wallet = $this->wallet();
        $this->attachBalances($wallet);

        return $this->success($request, (new WalletResource($wallet))->resolve($request));
    }

    public function transactions(Request $request)
    {
        $wallet = $this->wallet();
        $entries = PortalWalletLedger::where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => (string) $entry->id,
                    'type' => $entry->type,
                    'status' => $entry->status,
                    'bucket' => $entry->bucket,
                    'amount' => [
                        'currency' => $this->wallet()->currency,
                        'amountMinor' => (int) $entry->amount_minor,
                    ],
                    'referenceType' => $entry->reference_type,
                    'referenceId' => $entry->reference_id ? (string) $entry->reference_id : null,
                    'createdAt' => $entry->created_at ? $entry->created_at->toIso8601String() : null,
                ];
            })->values()->all();

        return $this->success($request, $entries);
    }

    public function topUp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'phone' => ['required', 'regex:/^\\+[1-9][0-9]{7,14}$/'],
            'provider' => ['required', 'in:MTN,Airtel,Zamtel'],
            'requestId' => ['required', 'string', 'max:120'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Enter a valid amount and mobile money account.', 422, $validator->errors()->toArray());
        $gateway = app(MobilePaymentGateway::class);
        if (!$gateway->enabled()) return $this->problem($request, 'PAYMENT_PROVIDER_NOT_CONFIGURED', 'Payment processing is not enabled.', 503);
        $wallet = $this->wallet();
        $created = false;
        $entry = DB::transaction(function () use ($wallet, $request, &$created) {
            PortalWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $existing = PortalWalletLedger::where('wallet_id', $wallet->id)->where('type', 'topup')
                ->where('metadata->requestId', $request->input('requestId'))->first();
            if ($existing) return $existing;
            $created = true;
            return PortalWalletLedger::create([
                'wallet_id' => $wallet->id, 'amount_minor' => (int) round($request->input('amount') * 100),
                'bucket' => 'pending', 'type' => 'topup', 'status' => 'pending',
                'reference_type' => 'mobile_money_deposit',
                'metadata' => ['requestId' => $request->input('requestId'), 'intentId' => (string) Str::uuid(),
                    'paymentStatus' => 'processing', 'phone' => $request->input('phone'), 'provider' => $request->input('provider')],
            ]);
        });
        if ($created) {
            try {
                $payload = $gateway->create([
                    'intentId' => $entry->metadata['intentId'], 'purpose' => 'wallet-topup',
                    'customerId' => (string) $wallet->client_id, 'method' => 'mobile-money',
                    'currency' => $wallet->currency, 'amountMinor' => $entry->amount_minor,
                    'phone' => $request->input('phone'), 'mobileMoneyProvider' => $request->input('provider'),
                ]);
                $entry = $this->applyTopUp($entry, $payload);
            } catch (\Throwable $e) {
                // A timeout may occur after the provider accepted payment. Retain
                // the pending entry so a retry cannot collect a second deposit.
                report($e);
            }
        }
        return $this->success($request, $this->depositPayload($entry), $created ? 201 : 200);
    }

    public function showTopUp(Request $request, $deposit)
    {
        $wallet = $this->wallet();
        $entry = PortalWalletLedger::whereKey($deposit)->where('wallet_id', $wallet->id)
            ->where('reference_type', 'mobile_money_deposit')->first();
        if (!$entry) return $this->problem($request, 'NOT_FOUND', 'Deposit not found.', 404);
        if ($entry->status === 'pending') {
            try {
                $payload = app(MobilePaymentGateway::class)->status($entry->metadata['intentId'], $entry->metadata['providerReference'] ?? null);
                if ($payload) $entry = $this->applyTopUp($entry, $payload);
            } catch (\Throwable $e) { report($e); }
        }
        return $this->success($request, $this->depositPayload($entry));
    }

    private function depositPayload($entry)
    {
        return ['id' => (string) $entry->id, 'status' => $entry->metadata['paymentStatus'] ?? 'processing'];
    }

    private function applyTopUp($entry, array $payload)
    {
        return DB::transaction(function () use ($entry, $payload) {
            $wallet = PortalWallet::whereKey($entry->wallet_id)->lockForUpdate()->firstOrFail();
            $entry = PortalWalletLedger::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($entry->status !== 'pending') return $entry;
            $gateway = app(MobilePaymentGateway::class);
            $status = (string) ($payload['status'] ?? 'processing');
            $metadata = $entry->metadata;
            $metadata['paymentStatus'] = $status;
            $metadata['providerReference'] = $payload['providerReference'] ?? ($metadata['providerReference'] ?? null);
            $entry->metadata = $metadata;
            if ($gateway->successful($status)) {
                $entry->bucket = 'available'; $entry->status = 'posted';
                $wallet->revision++; $wallet->save();
            } elseif ($gateway->failed($status)) { $entry->status = 'failed'; }
            $entry->save();
            return $entry;
        });
    }

    private function wallet()
    {
        $client = $this->customerContext->requireClient();

        return PortalWallet::firstOrCreate(
            ['client_id' => $client->id],
            ['currency' => config('customerportalapi.booking_pricing.currency', 'ZMW'), 'status' => 'active', 'revision' => 1]
        );
    }

    private function attachBalances($wallet)
    {
        $wallet->available_balance_minor = (int) PortalWalletLedger::where('wallet_id', $wallet->id)
            ->where('bucket', 'available')
            ->where('status', 'posted')
            ->sum('amount_minor');
        $wallet->pending_balance_minor = (int) PortalWalletLedger::where('wallet_id', $wallet->id)
            ->where('bucket', 'pending')
            ->whereIn('status', ['pending', 'posted'])
            ->sum('amount_minor');

        return $wallet;
    }
}

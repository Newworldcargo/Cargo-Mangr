<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $wallet = $this->wallet();
        $amountMinor = (int) round(((float) $request->input('amount')) * 100);

        DB::transaction(function () use ($wallet, $amountMinor, $request) {
            PortalWalletLedger::create([
                'wallet_id' => $wallet->id,
                'amount_minor' => $amountMinor,
                'bucket' => 'available',
                'type' => 'topup',
                'status' => 'posted',
                'reference_type' => 'customer_portal_topup',
                'metadata' => [
                    'source' => 'customer_portal_api',
                    'request_id' => (string) $request->attributes->get('portal_request_id'),
                ],
            ]);

            $wallet->revision = ((int) ($wallet->revision ?: 1)) + 1;
            $wallet->save();
        });

        $wallet = $wallet->fresh();
        $this->attachBalances($wallet);

        return $this->success($request, (new WalletResource($wallet))->resolve($request), 201);
    }

    private function wallet()
    {
        $client = $this->customerContext->requireClient();

        return PortalWallet::firstOrCreate(
            ['client_id' => $client->id],
            ['currency' => 'USD', 'status' => 'active', 'revision' => 1]
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

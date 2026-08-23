<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

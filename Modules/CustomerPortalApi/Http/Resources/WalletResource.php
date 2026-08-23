<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->client_id,
            'currency' => $this->currency,
            'availableBalance' => [
                'currency' => $this->currency,
                'amountMinor' => (int) ($this->available_balance_minor ?: 0),
            ],
            'pendingBalance' => [
                'currency' => $this->currency,
                'amountMinor' => (int) ($this->pending_balance_minor ?: 0),
            ],
            'status' => $this->status,
            'updatedAt' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }
}

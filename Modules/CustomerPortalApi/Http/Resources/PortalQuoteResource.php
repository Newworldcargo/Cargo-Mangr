<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PortalQuoteResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->client_id,
            'transportMode' => $this->transport_mode,
            'deliveryOption' => $this->delivery_option,
            'snapshot' => $this->snapshot,
            'currency' => $this->currency,
            'amountMinor' => (int) $this->amount_minor,
            'assumptions' => $this->assumptions ?: [],
            'status' => $this->expires_at && $this->expires_at->isPast() ? 'expired' : $this->status,
            'expiresAt' => $this->expires_at ? $this->expires_at->toIso8601String() : null,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }
}

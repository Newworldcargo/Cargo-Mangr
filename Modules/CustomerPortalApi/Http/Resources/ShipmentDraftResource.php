<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentDraftResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->client_id,
            'status' => $this->status,
            'payload' => $this->payload,
            'quoteId' => $this->quote_id ? (string) $this->quote_id : null,
            'shipmentId' => $this->shipment_id ? (string) $this->shipment_id : null,
            'expiresAt' => $this->expires_at ? $this->expires_at->toIso8601String() : null,
            'revision' => (int) ($this->revision ?: 1),
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updatedAt' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}

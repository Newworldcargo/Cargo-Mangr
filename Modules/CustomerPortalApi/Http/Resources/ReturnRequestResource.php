<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReturnRequestResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->client_id,
            'shipmentId' => (string) $this->shipment_id,
            'trackingNumber' => optional($this->shipment)->code,
            'reason' => $this->reason,
            'handover' => $this->handover,
            'status' => $this->status,
            'displayStatus' => $this->display_status,
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }
}

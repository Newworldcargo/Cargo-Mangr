<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PickupResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'customerId' => (string) $this->client_id,
            'shipmentId' => $this->shipment_id ? (string) $this->shipment_id : null,
            'status' => $this->status,
            'collectionPoint' => $this->collection_point,
            'scheduledDate' => $this->scheduled_date,
            'scheduledTime' => $this->scheduled_time,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }
}

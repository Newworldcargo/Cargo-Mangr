<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicTrackingResource extends JsonResource
{
    public function toArray($request)
    {
        $shipment = new ShipmentResource($this->resource);
        $payload = $shipment->resolve($request);

        unset($payload['customerId'], $payload['price'], $payload['allowedActions'], $payload['nextAction']);

        return $payload;
    }
}

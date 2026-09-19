<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicTrackingResource extends JsonResource
{
    public function toArray($request)
    {
        $shipment = new ShipmentResource($this->resource);
        $payload = $shipment->resolve($request);

        // This endpoint is unauthenticated: anyone holding a tracking number can
        // read it. Redaction here is a denylist, so every field added to
        // ShipmentResource is public by default and must be removed explicitly.
        unset(
            $payload['customerId'],
            $payload['price'],
            $payload['allowedActions'],
            $payload['nextAction'],
            $payload['confirmationCode']
        );

        return $payload;
    }
}

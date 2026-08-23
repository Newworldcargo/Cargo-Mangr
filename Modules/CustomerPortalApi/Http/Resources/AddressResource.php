<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => (string) $this->id,
            'label' => $this->label ?: null,
            'address' => $this->address,
            'countryId' => (string) $this->country_id,
            'stateId' => (string) $this->state_id,
            'areaId' => $this->area_id ? (string) $this->area_id : null,
            'streetAddressMap' => $this->client_street_address_map,
            'lat' => $this->client_lat,
            'lng' => $this->client_lng,
            'url' => $this->client_url,
            'isDefault' => (bool) $this->is_default,
            'revision' => (int) ($this->revision ?: 1),
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updatedAt' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}

<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalShipmentDraft extends Model
{
    protected $table = 'customer_portal_shipment_drafts';
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];
}

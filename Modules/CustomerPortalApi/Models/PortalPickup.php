<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Cargo\Entities\Shipment;

class PortalPickup extends Model
{
    protected $table = 'customer_portal_pickups';
    protected $guarded = [];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}

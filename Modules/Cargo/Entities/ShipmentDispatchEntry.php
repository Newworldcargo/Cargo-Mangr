<?php

namespace Modules\Cargo\Entities;

use Illuminate\Database\Eloquent\Model;

class ShipmentDispatchEntry extends Model
{
    protected $guarded = ['id'];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}

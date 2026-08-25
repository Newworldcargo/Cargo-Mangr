<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentChargeLine extends Model
{
    protected $table = 'shipment_charge_lines';

    protected $fillable = [
        'shipment_id',
        'description',
        'amount',
        'currency',
        'sort_order',
    ];

    protected $casts = [
        'amount'      => 'float',
        'sort_order'  => 'integer',
    ];

    public function shipment()
    {
        return $this->belongsTo(\Modules\Cargo\Entities\Shipment::class, 'shipment_id');
    }
}

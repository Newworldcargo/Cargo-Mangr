<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Cargo\Entities\Shipment;
use App\Models\User;

class ShipmentPaymentReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'method_of_payment',
        'amount',
        'currency',
        'receipt_number',
        'cashier_name',
        'user_id',
        'refunded',
        'refunded_at',
        'refund_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

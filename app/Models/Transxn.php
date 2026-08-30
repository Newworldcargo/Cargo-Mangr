<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Cargo\Entities\Shipment;
use App\Models\NwcReceipt;

class Transxn extends Model
{
    use HasFactory;
    protected $fillable = [
        'shipment_id',
        'cashier_user_id',
        'receipt_number',
        'discount_type',
        'discount_value',
        'total',
        'currency',
        'status',
        'refunded_at',
        'refund_reason',
        'refunded_amount',
    ];

    protected $casts = [
        'refunded_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function nwcReceipt()
    {
        return $this->hasOne(NwcReceipt::class, 'shipment_id', 'shipment_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function isRefunded()
    {
        return $this->status === 'refunded';
    }

    public function isRefundRequested()
    {
        return $this->status === 'refund_requested';
    }

    public function isPartiallyRefunded()
    {
        return $this->status === 'partially_refunded';
    }

    public function isCompleted()
    {
        return in_array($this->status, ['completed', 'refund_requested', 'partially_refunded'], true);
    }
}

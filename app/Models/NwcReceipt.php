<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Cargo\Entities\Shipment;

class NwcReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'receipt_number',
        'rate',
        'bill_usd',
        'bill_kwacha',
        'payment_currency',
        'method_of_payment',
        'discount_type',
        'discount_value',
        'cashier_name',
        'user_id',
    ];

    protected $casts = [
        'rate'           => 'float',
        'bill_usd'       => 'float',
        'bill_kwacha'    => 'float',
        'payment_currency' => 'string',
        'discount_value' => 'float',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}

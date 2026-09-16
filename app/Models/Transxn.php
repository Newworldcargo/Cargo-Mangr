<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Cargo\Entities\Shipment;
use App\Models\NwcReceipt;
use Modules\Cargo\Entities\Branch;

class Transxn extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_REFUND_REQUESTED = 'refund_requested';
    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_VOIDED_DUPLICATE = 'voided_duplicate';

    public static function settledStatuses(): array
    {
        return [
            self::STATUS_COMPLETED,
            self::STATUS_REFUND_REQUESTED,
            self::STATUS_PARTIALLY_REFUNDED,
        ];
    }
    protected $fillable = [
        'shipment_id',
        'cashier_user_id',
        'collection_branch_id',
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

    public function collectionBranch()
    {
        return $this->belongsTo(Branch::class, 'collection_branch_id');
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
        return in_array($this->status, self::settledStatuses(), true);
    }

    public function isVoidedDuplicate(): bool
    {
        return $this->status === self::STATUS_VOIDED_DUPLICATE;
    }
}

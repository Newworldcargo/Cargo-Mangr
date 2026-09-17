<?php

namespace Modules\Cargo\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OnlineBookingRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
        'processed_at' => 'datetime',
        'quoted_amount' => 'decimal:2',
        'total_weight' => 'decimal:3',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}

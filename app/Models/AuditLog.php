<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'shipment_id',
        'consignment_id',
        'branch_id',
        'branch_id',
        'event',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // The user who performed the action
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // The model being audited
    public function auditable()
    {
        return $this->morphTo();
    }
}

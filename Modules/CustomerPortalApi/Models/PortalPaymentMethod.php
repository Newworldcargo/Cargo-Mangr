<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalPaymentMethod extends Model
{
    protected $table = 'customer_portal_payment_methods';
    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
        'archived_at' => 'datetime',
    ];
}

<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalQuote extends Model
{
    protected $table = 'customer_portal_quotes';
    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'assumptions' => 'array',
        'expires_at' => 'datetime',
        'amount_minor' => 'integer',
    ];
}

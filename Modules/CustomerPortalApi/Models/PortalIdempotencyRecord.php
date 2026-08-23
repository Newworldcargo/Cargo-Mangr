<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalIdempotencyRecord extends Model
{
    protected $table = 'customer_portal_idempotency';
    protected $guarded = [];

    protected $casts = [
        'response_body' => 'array',
        'response_status' => 'integer',
        'expires_at' => 'datetime',
    ];
}

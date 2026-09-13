<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalPushToken extends Model
{
    protected $table = 'customer_portal_push_tokens';
    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}

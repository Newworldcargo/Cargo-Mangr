<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalFile extends Model
{
    protected $table = 'customer_portal_files';
    protected $guarded = [];

    protected $casts = [
        'size_bytes' => 'integer',
        'expires_at' => 'datetime',
    ];
}

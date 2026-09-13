<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalAccountPreference extends Model
{
    protected $table = 'customer_portal_account_preferences';
    protected $guarded = [];

    protected $casts = [
        'marketing_enabled' => 'boolean',
        'data_export_requested_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
    ];
}

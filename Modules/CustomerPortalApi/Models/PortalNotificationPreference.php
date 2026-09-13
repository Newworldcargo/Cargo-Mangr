<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalNotificationPreference extends Model
{
    protected $table = 'customer_portal_notification_preferences';
    protected $guarded = [];

    protected $casts = [
        'shipment_updates' => 'boolean',
        'bill_updates' => 'boolean',
        'marketing' => 'boolean',
    ];
}

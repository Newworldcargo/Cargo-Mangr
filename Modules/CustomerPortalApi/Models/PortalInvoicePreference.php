<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalInvoicePreference extends Model
{
    protected $table = 'customer_portal_invoice_preferences';
    protected $guarded = [];

    protected $casts = [
        'reminder_enabled' => 'boolean',
        'disputed_at' => 'datetime',
    ];
}

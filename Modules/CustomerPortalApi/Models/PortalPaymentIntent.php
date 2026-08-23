<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalPaymentIntent extends Model
{
    protected $table = 'customer_portal_payment_intents';
    protected $guarded = [];

    protected $hidden = [
        'client_token',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
    ];
}

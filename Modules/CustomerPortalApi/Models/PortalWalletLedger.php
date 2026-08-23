<?php

namespace Modules\CustomerPortalApi\Models;

use Illuminate\Database\Eloquent\Model;

class PortalWalletLedger extends Model
{
    protected $table = 'customer_portal_wallet_ledger';
    protected $guarded = [];

    protected $casts = [
        'amount_minor' => 'integer',
        'reference_id' => 'integer',
        'metadata' => 'array',
    ];
}

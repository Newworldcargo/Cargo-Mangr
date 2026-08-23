<?php

namespace Modules\CustomerPortalApi\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PortalBffSession extends Model
{
    protected $table = 'customer_portal_bff_sessions';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}

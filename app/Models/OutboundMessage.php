<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OutboundMessage extends Model
{
    protected $table = 'messaging_outbox';
    protected $guarded = [];
    protected $hidden = ['recipient', 'content'];
    protected $casts = ['recipient' => 'encrypted', 'content' => 'encrypted:array', 'available_at' => 'datetime', 'expires_at' => 'datetime', 'started_at' => 'datetime', 'accepted_at' => 'datetime'];
    public function lane(): string
    {
        return $this->purpose === 'otp' ? 'otp' : (str_starts_with($this->purpose, 'bulk_') ? 'bulk' : 'notifications');
    }
}

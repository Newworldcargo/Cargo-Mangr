<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MessagingSetting extends Model
{
    protected $guarded = [];
    protected $hidden = ['password'];
    protected $casts = ['password' => 'encrypted', 'sms_purposes' => 'array', 'sms_enabled' => 'boolean', 'email_enabled' => 'boolean'];

    public static function current(): self
    {
        return (Schema::hasTable('messaging_settings') ? static::find(1) : null)
            ?? new static(['sms_enabled' => false, 'email_enabled' => false, 'sms_purposes' => [], 'sms_per_minute' => 30, 'email_per_minute' => 30]);
    }
    public function allows(string $channel, string $purpose): bool
    {
        if (!config('messaging.activation_ready')) return false;
        if (!array_key_exists($purpose, config('messaging.purposes'))) return false;
        return $channel === 'email' ? $this->email_enabled
            : ($channel === 'sms' && $this->sms_enabled && in_array($purpose, $this->sms_purposes ?? [], true));
    }
}

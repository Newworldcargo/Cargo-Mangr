<?php
namespace App\Services\Messaging;

use App\Jobs\SendOutboundMessage;
use App\Models\MessagingSetting;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\DB;

class Outbox
{
    private $settings;
    public static function phone(string $value): ?string
    {
        $value = preg_replace('/[\s()+.\-]/', '', trim($value));
        if (str_starts_with($value, '00')) $value = substr($value, 2);
        if (preg_match('/^0[679]\d{8}$/', $value)) $value = '260' . substr($value, 1);
        if (preg_match('/^[679]\d{8}$/', $value)) $value = '260' . $value;
        return preg_match('/^260[679]\d{8}$/', $value) ? $value : null;
    }

    public function enqueue(string $channel, string $purpose, string $recipient, array $content, string $key, $expiresAt = null, ?int $campaignId = null): ?OutboundMessage
    {
        $this->settings ??= MessagingSetting::current();
        if (!$this->settings->allows($channel, $purpose)) return null;
        $recipient = $channel === 'sms' ? self::phone($recipient) : (filter_var(trim($recipient), FILTER_VALIDATE_EMAIL) ? strtolower(trim($recipient)) : null);
        if (!$recipient) return null;
        $dedupe = hash('sha256', $channel . ':' . $key . ':' . $recipient);
        return DB::transaction(function () use ($channel, $purpose, $recipient, $content, $dedupe, $expiresAt, $campaignId) {
            // A unique key protects callers that retry the same business operation.
            try {
                $message = OutboundMessage::firstOrCreate(['dedupe_key' => $dedupe], [
                    'channel' => $channel, 'purpose' => $purpose, 'recipient' => $recipient,
                    'content' => $content, 'campaign_id' => $campaignId,
                    'available_at' => now(), 'expires_at' => $expiresAt ?? now()->addDay(),
                ]);
            } catch (\Illuminate\Database\QueryException $error) {
                $message = OutboundMessage::where('dedupe_key', $dedupe)->first();
                if (!$message) throw $error;
            }
            if ($message->wasRecentlyCreated) SendOutboundMessage::dispatch($message->id)->onConnection('messaging')->onQueue($message->lane())->afterCommit();
            return $message;
        });
    }
}

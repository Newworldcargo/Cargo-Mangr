<?php
namespace App\Jobs;

use App\Models\MessagingSetting;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Services\Messaging\MtnClient;
use App\Services\Messaging\RejectedSend;
use App\Services\Messaging\RetryableSend;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class SendOutboundMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    public $tries = 0;
    public $timeout = 45;
    public $failOnTimeout = true;
    public $messageId;

    public function __construct(int $messageId) { $this->messageId = $messageId; }

    public function handle(MtnClient $mtn): void
    {
        $message = OutboundMessage::find($this->messageId);
        if (!$message || $message->status !== 'pending') return;
        if ($message->expires_at->isPast()) { $message->update(['status' => 'expired']); return; }
        if ($message->available_at->isFuture()) { $this->release(max(1, now()->diffInSeconds($message->available_at))); return; }
        $settings = MessagingSetting::current();
        if (!$settings->allows($message->channel, $message->purpose)) { $message->update(['status' => 'suppressed']); return; }
        if ($message->purpose === 'otp' && isset($message->content['user_id'])) {
            $user = User::find($message->content['user_id']);
            if (!$user || !hash_equals((string) $user->otp, (string) ($message->content['otp'] ?? '')) || !$user->otp_expires_at || \Carbon\Carbon::parse($user->otp_expires_at)->isPast()) {
                $message->update(['status' => 'expired']); return;
            }
        }
        // Separate budgets reserve capacity for OTP even during a large broadcast.
        $rateKey = 'messaging-rate:' . $message->channel . ':' . $message->lane();
        $lock = Cache::lock($rateKey . ':lock', 5);
        if (!$lock->get()) { $this->release(2); return; }
        try {
            $limit = max(1, intdiv((int) $settings->{$message->channel . '_per_minute'}, 3));
            if (RateLimiter::tooManyAttempts($rateKey, $limit)) { $this->release(max(1, RateLimiter::availableIn($rateKey))); return; }
            RateLimiter::hit($rateKey, 60);
        } finally { $lock->release(); }
        $claimed = OutboundMessage::whereKey($message->id)->where('status', 'pending')->update(['status' => 'processing', 'started_at' => now(), 'attempts' => $message->attempts + 1]);
        if (!$claimed) return;
        $message->refresh();
        try {
            $providerId = null;
            if ($message->channel === 'sms') $providerId = $mtn->send($message, $settings);
            else {
                $content = $message->content;
                if ($message->purpose === 'otp') Mail::to($message->recipient)->send(new \App\Mail\OTPMail($content['otp'], $content['name'] ?? 'Customer'));
                else Mail::raw($content['body'], function ($mail) use ($message, $content) { $mail->to($message->recipient)->subject($content['subject'] ?? 'New World Cargo'); });
            }
            $message->update(['status' => 'accepted', 'accepted_at' => now(), 'provider_id' => $providerId, 'last_error' => null]);
        } catch (RetryableSend $error) {
            $attempts = $message->fresh()->attempts;
            $delay = min(900, 30 * (2 ** min($attempts, 5)));
            $message->update(['status' => $attempts < 5 ? 'pending' : 'failed', 'available_at' => now()->addSeconds($delay), 'last_error' => $error->getMessage()]);
            if ($attempts < 5) $this->release($delay);
        } catch (RejectedSend $error) {
            $message->update(['status' => 'failed', 'last_error' => $error->getMessage()]);
        } catch (\Throwable $error) {
            $message->update(['status' => 'unknown', 'last_error' => 'Delivery acceptance is uncertain. Reconcile with the provider before resending.']);
        }
    }
    public function failed(\Throwable $error): void
    {
        OutboundMessage::whereKey($this->messageId)->where('status', 'processing')->update(['status' => 'unknown', 'last_error' => 'Worker interrupted. Check provider history before resending.']);
    }
}

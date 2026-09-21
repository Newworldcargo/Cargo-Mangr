<?php
namespace App\Jobs;

use App\Models\MessagingCampaign;
use App\Models\MessagingSetting;
use App\Models\User;
use App\Services\Messaging\Outbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

class ExpandMessagingCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    public $campaignId;
    public $tries = 3;
    public $timeout = 60;
    public $backoff = 30;
    public function __construct(int $id) { $this->campaignId = $id; }
    public function handle(Outbox $outbox): void
    {
        $lock = Cache::lock('messaging-campaign:' . $this->campaignId, 80);
        if (!$lock->get()) { $this->release(10); return; }
        try {
            $campaign = MessagingCampaign::find($this->campaignId);
            if (!$campaign || !in_array($campaign->status, ['pending', 'expanding'], true)) return;
            $purpose = 'bulk_' . $campaign->audience;
            if (!MessagingSetting::current()->allows($campaign->channel, $purpose)) { $campaign->update(['status' => 'suppressed']); return; }
            if ($campaign->created_at->copy()->addDay()->isPast()) { $campaign->update(['status' => 'expired']); return; }
            // A cursor and snapshot ceiling bound each job and exclude later registrations.
            $users = User::where('role', $campaign->audience === 'customers' ? 4 : User::STAFF)
                ->where('id', '>', $campaign->cursor)->where('id', '<=', $campaign->last_user_id)
                ->orderBy('id')->limit(100)->get();
            foreach ($users as $user) {
                $recipient = $user->email;
                if ($campaign->channel === 'sms') {
                    $recipient = $user->responsible_mobile ?: $user->secondary_mobile;
                    if (!$recipient) {
                        $entity = $campaign->audience === 'customers' ? \Modules\Cargo\Entities\Client::class : \Modules\Cargo\Entities\Staff::class;
                        $recipient = $entity::where('user_id', $user->id)->value('responsible_mobile');
                    }
                }
                $message = $outbox->enqueue($campaign->channel, $purpose, (string) $recipient, $campaign->content,
                    'campaign:' . $campaign->id, $campaign->created_at->copy()->addDay(), $campaign->id);
                $campaign->cursor = $user->id;
                $campaign->queued += $message && $message->wasRecentlyCreated ? 1 : 0;
                $campaign->skipped += $message ? 0 : 1;
                $campaign->status = 'expanding';
                $campaign->save();
            }
            if ($users->count() === 100) static::dispatch($campaign->id)->onConnection('messaging')->onQueue('campaigns');
            else $campaign->update(['status' => 'queued']);
        } finally { $lock->release(); }
    }
}

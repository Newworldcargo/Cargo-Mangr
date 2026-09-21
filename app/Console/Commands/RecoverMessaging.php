<?php
namespace App\Console\Commands;

use App\Jobs\SendOutboundMessage;
use App\Jobs\ExpandMessagingCampaign;
use App\Models\OutboundMessage;
use App\Models\MessagingCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RecoverMessaging extends Command
{
    protected $signature = 'messaging:recover';
    protected $description = 'Recover unscheduled messages without replaying uncertain sends';
    public function handle(): int
    {
        if (!Schema::hasTable('messaging_outbox')) return 0;
        OutboundMessage::where('status', 'processing')->where('started_at', '<', now()->subMinutes(3))
            ->update(['status' => 'unknown', 'last_error' => 'Worker interrupted. Check provider history before resending.']);
        OutboundMessage::where('status', 'pending')->where('expires_at', '<=', now())->update(['status' => 'expired']);
        OutboundMessage::where('status', 'pending')->where('available_at', '<=', now())->where('updated_at', '<', now()->subMinutes(5))
            ->orderBy('id')->limit(500)->get()->each(function ($message) {
                SendOutboundMessage::dispatch($message->id)->onConnection('messaging')->onQueue($message->lane());
                $message->touch();
            });
        MessagingCampaign::whereIn('status', ['pending', 'expanding'])->where('updated_at', '<', now()->subMinutes(5))->limit(10)->get()->each(function ($campaign) {
            ExpandMessagingCampaign::dispatch($campaign->id)->onConnection('messaging')->onQueue('campaigns'); $campaign->touch();
        });
        return 0;
    }
}

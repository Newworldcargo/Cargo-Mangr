<?php
namespace Tests\Feature;

use App\Jobs\ExpandMessagingCampaign;
use App\Jobs\SendOutboundMessage;
use App\Models\MessagingCampaign;
use App\Models\MessagingSetting;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Services\Messaging\MtnClient;
use App\Services\Messaging\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;
    private $realQueue;

    protected function setUp(): void
    {
        parent::setUp();
        config(['messaging.activation_ready' => true]);
        $this->realQueue = app('queue');
        Queue::fake(); Cache::flush(); Http::swap(new \Illuminate\Http\Client\Factory()); Mail::fake();
    }
    private function enable(array $overrides = []): MessagingSetting
    {
        return MessagingSetting::create(array_merge(['id' => 1, 'sms_enabled' => true, 'email_enabled' => true,
            'email' => 'test@example.test', 'password' => 'test-secret', 'sender_txn' => 'NWCTest', 'sender_otp' => 'NWCTestOTP',
            'sms_purposes' => array_keys(config('messaging.purposes')), 'sms_per_minute' => 600, 'email_per_minute' => 600], $overrides));
    }
    private function enqueue(string $key = 'test'): OutboundMessage
    {
        return app(Outbox::class)->enqueue('sms', 'customer_notifications', '0970000000', ['body' => 'Test message'], $key);
    }
    private function user(int $role = 4): User
    {
        return User::create(['name' => 'Test', 'email' => Str::uuid() . '@example.test', 'password' => bcrypt('test'), 'role' => $role, 'verified' => true]);
    }
    private function process(OutboundMessage $message): void
    {
        (new SendOutboundMessage($message->id))->handle(app(MtnClient::class));
    }

    public function test_disabled_by_default_and_per_purpose(): void
    {
        $this->assertNull(app(Outbox::class)->enqueue('sms', 'otp', '0970000000', ['body' => 'Test'], 'one'));
        $this->enable(['sms_purposes' => ['otp']]);
        $this->assertNull(app(Outbox::class)->enqueue('sms', 'bulk_staff', '0970000000', ['body' => 'Test'], 'two'));
        Queue::assertNothingPushed(); Http::assertNothingSent();
    }
    public function test_encryption_deduplication_and_dedicated_queue(): void
    {
        $this->enable();
        $first = $this->enqueue(); $second = $this->enqueue();
        $this->assertSame($first->id, $second->id);
        $this->assertSame('260970000000', $first->recipient);
        $this->assertStringNotContainsString('260970000000', DB::table('messaging_outbox')->value('recipient'));
        $this->assertStringNotContainsString('Test message', DB::table('messaging_outbox')->value('content'));
        $this->assertNotSame('test-secret', DB::table('messaging_settings')->value('password'));
        Queue::assertPushed(SendOutboundMessage::class, fn ($job) => $job->connection === 'messaging' && $job->queue === 'notifications');
        Queue::assertPushed(SendOutboundMessage::class, 1);
        Http::assertNothingSent();
    }
    public function test_phone_normalisation_is_zambia_only(): void
    {
        foreach (['0970000000', '+260970000000', '00260970000000', '970000000'] as $number) $this->assertSame('260970000000', Outbox::phone($number));
        foreach (['+263770000000', 'hello', '260123', '0970000000ext42'] as $number) $this->assertNull(Outbox::phone($number));
    }
    public function test_acceptance_is_not_delivery_and_duplicate_jobs_do_not_resend(): void
    {
        $this->enable();
        Http::fake(['*/login' => Http::response(['access_token' => 'fake-token']), '*/sms/send' => Http::response(['statusCode' => 0, 'txnId' => 'test-txn'], 202)]);
        $message = $this->enqueue(); $this->process($message); $this->process($message);
        $this->assertSame('accepted', $message->fresh()->status);
        $this->assertSame('test-txn', $message->fresh()->provider_id);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sms/send') && $request['recipient'] === '260970000000' && $request['category'] === 'TXN');
    }
    public function test_ambiguous_timeout_is_not_automatically_retried(): void
    {
        $this->enable();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/login')) return Http::response(['access_token' => 'fake-token']);
            throw new \Illuminate\Http\Client\ConnectionException('Timeout including sensitive provider data');
        });
        $message = $this->enqueue(); $this->process($message);
        $this->assertSame('unknown', $message->fresh()->status);
        $this->assertStringNotContainsString('sensitive', $message->fresh()->last_error);
        $this->process($message);
        $this->assertSame(1, $message->fresh()->attempts);
    }
    public function test_rate_limit_retries_are_bounded_and_wait_before_sending(): void
    {
        $this->enable();
        Http::fake(['*/login' => Http::response(['access_token' => 'fake-token']), '*/sms/send' => Http::response([], 429)]);
        $message = $this->enqueue(); $this->process($message);
        $this->assertSame('pending', $message->fresh()->status);
        $this->process($message);
        $this->assertSame(1, $message->fresh()->attempts);
        for ($i = 0; $i < 4; $i++) { $this->travel(16)->minutes(); $this->process($message); }
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame(5, $message->fresh()->attempts);
    }
    public function test_expiry_superseded_otp_and_kill_switch(): void
    {
        $settings = $this->enable();
        $user = $this->user(); $user->forceFill(['otp' => '222222', 'otp_expires_at' => now()->addMinutes(10)])->save();
        $message = app(Outbox::class)->enqueue('sms', 'otp', '0970000000', ['body' => 'Old OTP', 'otp' => '111111', 'user_id' => $user->id], 'old-otp');
        $this->process($message); $this->assertSame('expired', $message->fresh()->status);
        $message = $this->enqueue('disabled'); $settings->update(['sms_enabled' => false]);
        $this->process($message); $this->assertSame('suppressed', $message->fresh()->status);
        Http::assertNothingSent();
    }
    public function test_bulk_expansion_is_chunked_and_resumable(): void
    {
        $this->enable();
        for ($i = 0; $i < 205; $i++) DB::table('users')->insert(['name' => 'Bulk test', 'email' => "bulk$i@example.test", 'password' => 'unused', 'role' => 4]);
        $campaign = MessagingCampaign::create(['request_key' => (string) Str::uuid(), 'created_by' => 1, 'channel' => 'email', 'audience' => 'customers', 'content' => ['subject' => 'Service update', 'body' => 'Test'], 'last_user_id' => User::max('id')]);
        $job = new ExpandMessagingCampaign($campaign->id);
        $job->handle(app(Outbox::class)); $this->assertSame(100, OutboundMessage::count());
        $job->handle(app(Outbox::class)); $job->handle(app(Outbox::class)); $job->handle(app(Outbox::class));
        $this->assertSame(205, OutboundMessage::count());
        $this->assertSame('queued', $campaign->fresh()->status);
        Mail::assertNothingSent(); Http::assertNothingSent();
    }
    public function test_protected_settings_keep_password_private_and_allow_disabling_every_purpose(): void
    {
        DB::table('currencies')->where('code', 'USD')->update(['default' => 1]);
        $this->enable(); $customer = $this->user(); $admin = $this->user(User::ADMIN);
        $this->actingAs($customer)->get('/messaging')->assertForbidden();
        $this->actingAs($customer)->post('/messaging/settings', [])->assertForbidden();
        $response = $this->actingAs($admin)->get('/messaging')->assertOk()->assertDontSee('test-secret');
        if ($path = getenv('MESSAGING_RENDER_PATH')) file_put_contents($path, $response->getContent());
        $this->actingAs($admin)->post('/messaging/settings', ['sms_enabled' => 0, 'email_enabled' => 0, 'sms_per_minute' => 30, 'email_per_minute' => 30])->assertRedirect();
        $this->assertFalse(MessagingSetting::current()->sms_enabled);
        $this->assertSame([], MessagingSetting::current()->sms_purposes);
        $this->assertSame('test-secret', MessagingSetting::current()->password);
    }
    public function test_recovery_never_replays_interrupted_sends(): void
    {
        $this->enable(); $message = $this->enqueue();
        $message->update(['status' => 'processing', 'started_at' => now()->subMinutes(10)]);
        $this->artisan('messaging:recover')->assertExitCode(0);
        $this->assertSame('unknown', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_activation_gate_blocks_enabling_and_campaigns(): void
    {
        $this->enable(); config(['messaging.activation_ready' => false]);
        $admin = $this->user(User::ADMIN);
        $this->actingAs($admin)->postJson('/messaging/settings', ['sms_enabled' => true, 'email_enabled' => false, 'sms_per_minute' => 30, 'email_per_minute' => 30])->assertStatus(422);
        $this->actingAs($admin)->postJson('/messaging/campaigns', ['request_key' => (string) Str::uuid(), 'channel' => 'sms', 'audience' => 'customers', 'subject' => 'Test', 'body' => 'Test', 'confirm_service_message' => true])->assertStatus(422);
        $this->assertSame(0, MessagingCampaign::count()); Http::assertNothingSent();
    }

    public function test_campaign_double_submission_is_idempotent_and_permission_protected(): void
    {
        $this->enable(); $admin = $this->user(User::ADMIN);
        $data = ['request_key' => (string) Str::uuid(), 'channel' => 'email', 'audience' => 'staff', 'subject' => 'Test', 'body' => 'Test', 'confirm_service_message' => true];
        $this->actingAs($this->user())->postJson('/messaging/campaigns', $data)->assertForbidden();
        $this->actingAs($admin)->post('/messaging/campaigns', $data)->assertRedirect();
        $this->actingAs($admin)->post('/messaging/campaigns', $data)->assertRedirect();
        $this->assertSame(1, MessagingCampaign::count());
        Queue::assertPushed(ExpandMessagingCampaign::class, 1);
    }

    public function test_notification_mail_and_sms_are_queued_without_provider_calls(): void
    {
        $this->enable(); $user = $this->user();
        $notification = new \App\Notifications\GlobalNotification(['phone' => '0970000000', 'message' => ['subject' => 'Shipment ready', 'content' => 'Collect your shipment', 'url' => 'https://example.test/shipments/1']], ['mail', 'sms', 'database']);
        $this->assertContains(\App\Channels\QueuedNotificationMailChannel::class, $notification->via($user));
        $notification->id = (string) Str::uuid();
        (new \App\Channels\QueuedNotificationMailChannel())->send($user, $notification);
        $notification->toSms($user);
        $this->assertSame(2, OutboundMessage::count());
        Queue::assertPushed(SendOutboundMessage::class, 2); Http::assertNothingSent(); Mail::assertNothingSent();
    }

    public function test_otp_email_is_expiry_checked_before_mail_submission(): void
    {
        $this->enable(); $user = $this->user();
        $user->forceFill(['otp' => '123456', 'otp_expires_at' => now()->addMinutes(10)])->save();
        $message = app(Outbox::class)->enqueue('email', 'otp', $user->email, ['otp' => '123456', 'user_id' => $user->id, 'name' => 'Test'], 'otp-test', $user->otp_expires_at);
        $this->process($message);
        Mail::assertSent(\App\Mail\OTPMail::class, 1);
        $this->assertSame('accepted', $message->fresh()->status);
    }

    public function test_bulk_budget_does_not_consume_otp_budget(): void
    {
        $this->enable(['sms_per_minute' => 3]);
        Http::fake(['*/login' => Http::response(['access_token' => 'fake-token']), '*/sms/send' => Http::response(['statusCode' => 0, 'txnId' => 'test-txn'], 202)]);
        $bulk = app(Outbox::class)->enqueue('sms', 'bulk_staff', '0970000000', ['body' => 'Test'], 'bulk-one');
        $blocked = app(Outbox::class)->enqueue('sms', 'bulk_staff', '0970000001', ['body' => 'Test'], 'bulk-two');
        $otp = app(Outbox::class)->enqueue('sms', 'otp', '0970000000', ['body' => 'Test OTP'], 'otp-one');
        $this->process($bulk); $this->process($blocked); $this->process($otp);
        $this->assertSame('accepted', $bulk->fresh()->status);
        $this->assertSame('pending', $blocked->fresh()->status);
        $this->assertSame('accepted', $otp->fresh()->status);
    }

    public function test_real_database_worker_consumes_id_only_job_while_default_is_sync(): void
    {
        $this->enable();
        Http::fake(['*/login' => Http::response(['access_token' => 'fake-token']), '*/sms/send' => Http::response(['statusCode' => 0, 'txnId' => 'worker-test'], 202)]);
        $message = $this->enqueue('worker');
        Queue::swap($this->realQueue);
        $job = (new SendOutboundMessage($message->id))->onConnection('messaging')->onQueue('notifications')->beforeCommit();
        \Illuminate\Support\Facades\Bus::dispatch($job);
        $this->assertSame('sync', config('queue.default'));
        $this->assertSame(1, DB::table('messaging_jobs')->count());
        $payload = DB::table('messaging_jobs')->value('payload');
        $this->assertStringNotContainsString('Test message', $payload);
        $this->assertStringNotContainsString('260970000000', $payload);
        $this->artisan('queue:work', ['connection' => 'messaging', '--queue' => 'notifications', '--once' => true, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame('accepted', $message->fresh()->status);
        $this->assertSame(0, DB::table('messaging_jobs')->count());
    }

    public function test_bad_credentials_do_not_trigger_a_login_storm(): void
    {
        $this->enable(); Http::fake(['*/login' => Http::response([], 401)]);
        $one = $this->enqueue('one'); $two = $this->enqueue('two');
        $this->process($one); $this->process($two);
        Http::assertSentCount(1);
        $this->assertSame('failed', $one->fresh()->status);
        $this->assertSame('failed', $two->fresh()->status);
    }
}

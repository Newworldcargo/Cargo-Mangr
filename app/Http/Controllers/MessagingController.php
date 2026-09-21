<?php
namespace App\Http\Controllers;

use App\Jobs\ExpandMessagingCampaign;
use App\Models\MessagingCampaign;
use App\Models\MessagingSetting;
use App\Models\OutboundMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MessagingController extends Controller
{
    private function allowed(string $permission): bool
    {
        return (int) auth()->user()->role === User::ADMIN || auth()->user()->can($permission);
    }
    public function index()
    {
        $manage = $this->allowed('manage-messaging-settings');
        $send = $this->allowed('send-bulk-messages');
        $history = $this->allowed('view-messaging-history');
        abort_unless($manage || $send || $history, 403);
        $settings = MessagingSetting::current();
        $messages = $history ? OutboundMessage::latest('id')->paginate(25) : null;
        $counts = $history ? OutboundMessage::select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status') : [];
        return view('messaging.index', compact('settings', 'manage', 'send', 'history', 'messages', 'counts'));
    }
    public function store(Request $request)
    {
        abort_unless($this->allowed('manage-messaging-settings'), 403);
        $input = $request->validate([
            'sms_enabled' => ['required', 'boolean'], 'email_enabled' => ['required', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'], 'password' => ['nullable', 'string', 'max:1000'],
            'sender_txn' => ['nullable', 'regex:/^[A-Za-z0-9]{1,11}$/'],
            'sender_otp' => ['nullable', 'regex:/^[A-Za-z0-9]{1,11}$/'],
            'sms_purposes' => ['sometimes', 'array'], 'sms_purposes.*' => [Rule::in(array_keys(config('messaging.purposes')))],
            'sms_per_minute' => ['required', 'integer', 'min:3', 'max:600'],
            'email_per_minute' => ['required', 'integer', 'min:3', 'max:600'],
        ]);
        $settings = MessagingSetting::current();
        if (empty($input['password'])) unset($input['password']);
        if (($input['sms_enabled'] || $input['email_enabled']) && !config('messaging.activation_ready')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['sms_enabled' => 'Messaging activation is pending worker and provider verification. Settings can be saved with sending disabled.']);
        }
        $input['sms_purposes'] = array_values(array_unique($input['sms_purposes'] ?? []));
        $settings->fill($input);
        if ($settings->sms_enabled) {
            $errors = [];
            if (!$settings->email || !$settings->password) $errors['email'] = 'An MTN enterprise account is required.';
            if (in_array('otp', $settings->sms_purposes, true) && !$settings->sender_otp) $errors['sender_otp'] = 'An approved OTP sender is required.';
            if (array_diff($settings->sms_purposes, ['otp']) && !$settings->sender_txn) $errors['sender_txn'] = 'An approved transactional sender is required.';
            if ($errors) throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
        $settings->id = 1; $settings->save();
        \Log::info('Messaging settings updated', ['actor_id' => auth()->id(), 'sms_enabled' => $settings->sms_enabled, 'email_enabled' => $settings->email_enabled, 'purposes' => $settings->sms_purposes]);
        return back()->with('success', 'Messaging settings saved.');
    }
    public function campaign(Request $request)
    {
        abort_unless($this->allowed('send-bulk-messages'), 403);
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'channel' => ['required', Rule::in(['sms', 'email'])],
            'audience' => ['required', Rule::in(['customers', 'staff'])],
            'subject' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:1530'],
            'confirm_service_message' => ['accepted'],
        ]);
        if (!MessagingSetting::current()->allows($data['channel'], 'bulk_' . $data['audience'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['channel' => 'This messaging channel or purpose is disabled.']);
        }
        DB::transaction(function () use ($data) {
            $campaign = MessagingCampaign::firstOrCreate(['request_key' => $data['request_key']], [
                'created_by' => auth()->id(), 'channel' => $data['channel'], 'audience' => $data['audience'],
                'content' => ['subject' => $data['subject'], 'body' => $data['body']], 'last_user_id' => User::max('id') ?? 0,
            ]);
            abort_unless((int) $campaign->created_by === (int) auth()->id(), 403);
            if ($campaign->wasRecentlyCreated) ExpandMessagingCampaign::dispatch($campaign->id)->onConnection('messaging')->onQueue('campaigns')->afterCommit();
        });
        return back()->with('success', 'Announcement queued. Acceptance and delivery are separate stages.');
    }
}

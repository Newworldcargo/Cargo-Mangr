@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Messaging')
@section('content')
<div class="container-fluid py-4">
    <h1 class="h3">Messaging</h1>
    @unless(config('messaging.activation_ready'))<div class="alert alert-info" role="status">Setup mode. Live sending is locked until worker and provider verification is complete.</div>@endunless
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($manage)
    <form method="POST" action="{{ route('messaging.settings') }}" class="border-bottom py-3">
        @csrf
        <h2 class="h5">MTN Ngage SMS</h2>
        <div class="row">
            <div class="col-md-6 form-group"><label for="mtn-email">Enterprise account email</label><input id="mtn-email" name="email" type="email" class="form-control" value="{{ old('email', $settings->email) }}" autocomplete="off"></div>
            <div class="col-md-6 form-group"><label for="mtn-password">{{ $settings->password ? 'Replace account password (optional)' : 'Account password' }}</label><input id="mtn-password" name="password" type="password" class="form-control" autocomplete="new-password"></div>
            <div class="col-md-6 form-group"><label for="sender-txn">Approved transactional sender ID</label><input id="sender-txn" name="sender_txn" class="form-control" maxlength="11" value="{{ old('sender_txn', $settings->sender_txn) }}"></div>
            <div class="col-md-6 form-group"><label for="sender-otp">Approved OTP sender ID</label><input id="sender-otp" name="sender_otp" class="form-control" maxlength="11" value="{{ old('sender_otp', $settings->sender_otp) }}"></div>
        </div>
        <input type="hidden" name="sms_enabled" value="0">
        <div class="custom-control custom-switch mb-3"><input type="checkbox" class="custom-control-input" id="sms-enabled" name="sms_enabled" value="1" {{ old('sms_enabled', $settings->sms_enabled) ? 'checked' : '' }}><label class="custom-control-label" for="sms-enabled">Enable MTN SMS</label></div>
        <fieldset class="mb-3"><legend class="h6">SMS purposes</legend>
        @foreach(config('messaging.purposes') as $purpose => $label)
            <div class="custom-control custom-switch mb-2"><input type="checkbox" class="custom-control-input" id="purpose-{{ $purpose }}" name="sms_purposes[]" value="{{ $purpose }}" {{ in_array($purpose, old('sms_purposes', $settings->sms_purposes ?? [])) ? 'checked' : '' }}><label class="custom-control-label" for="purpose-{{ $purpose }}">{{ $label }}</label></div>
        @endforeach
        </fieldset>
        <input type="hidden" name="email_enabled" value="0">
        <div class="custom-control custom-switch mb-3"><input type="checkbox" class="custom-control-input" id="email-enabled" name="email_enabled" value="1" {{ old('email_enabled', $settings->email_enabled) ? 'checked' : '' }}><label class="custom-control-label" for="email-enabled">Queue service notification emails, portal OTP emails and bulk announcements</label></div>
        <div class="row">
            <div class="col-md-6 form-group"><label for="sms-rate">SMS submissions per minute</label><input type="number" min="3" max="600" class="form-control" id="sms-rate" name="sms_per_minute" value="{{ old('sms_per_minute', $settings->sms_per_minute) }}" required></div>
            <div class="col-md-6 form-group"><label for="email-rate">Emails per minute</label><input type="number" min="3" max="600" class="form-control" id="email-rate" name="email_per_minute" value="{{ old('email_per_minute', $settings->email_per_minute) }}" required></div>
        </div>
        <div class="alert alert-warning">Enable only after the messaging workers are running and the account, approved sender IDs, and provider limits have been verified. Turning off a channel suppresses its unsent queued messages. Existing legacy email paths are unchanged when email queueing is off.</div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-save mr-1"></i> Save settings</button>
    </form>
    @endif
    @if($send)
    <form method="POST" action="{{ route('messaging.campaign') }}" class="border-bottom py-4">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <h2 class="h5">Service announcement</h2>
        <div class="row"><div class="col-md-6 form-group"><label for="channel">Channel</label><select id="channel" name="channel" class="form-control"><option value="sms">SMS</option><option value="email">Email</option></select></div><div class="col-md-6 form-group"><label for="audience">Recipients</label><select id="audience" name="audience" class="form-control"><option value="staff">All staff accounts</option><option value="customers">All customer accounts</option></select></div></div>
        <div class="form-group"><label for="subject">Subject</label><input id="subject" name="subject" maxlength="150" class="form-control" value="{{ old('subject') }}" required></div>
        <div class="form-group"><label for="message-body">Message</label><textarea id="message-body" name="body" class="form-control" rows="4" maxlength="1530" required>{{ old('body') }}</textarea></div>
        <div class="custom-control custom-checkbox mb-3"><input id="confirm-send" name="confirm_service_message" value="1" type="checkbox" class="custom-control-input" required><label for="confirm-send" class="custom-control-label">I confirm this is a service announcement, not marketing, and authorise sending it to the selected audience.</label></div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane mr-1"></i> Queue announcement</button>
    </form>
    @endif
    @if($history)
    <section class="py-4"><h2 class="h5">Message history</h2>
        <div class="d-flex flex-wrap mb-3">@foreach($counts as $status => $total)<span class="mr-4 mb-2">{{ ucfirst($status) }}: <strong>{{ $total }}</strong></span>@endforeach</div>
        <p class="text-muted">Accepted means the provider accepted the request, not confirmed delivery. Unknown outcomes require provider reconciliation before resending.</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Channel</th><th>Purpose</th><th>Status</th><th>Attempts</th><th>Created</th><th>Result</th></tr></thead><tbody>
        @forelse($messages as $message)<tr><td>{{ $message->id }}</td><td>{{ strtoupper($message->channel) }}</td><td>{{ config('messaging.purposes.'.$message->purpose) }}</td><td>{{ ucfirst($message->status) }}</td><td>{{ $message->attempts }}</td><td>{{ $message->created_at }}</td><td>{{ $message->last_error ?: $message->provider_id }}</td></tr>@empty<tr><td colspan="7">No messages queued.</td></tr>@endforelse
        </tbody></table></div>{{ $messages->links() }}
    </section>
    @endif
</div>
@endsection

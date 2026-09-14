<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use App\Mail\OTPMail;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PortalOtpNotifier
{
    public function sendVerification(User $user): void
    {
        $otp = (string) $user->otp;
        if ($otp === '') {
            return;
        }

        $this->sendEmail($user, $otp);
        $this->sendSms($user, $otp);
    }

    private function sendEmail(User $user, string $otp): void
    {
        if (!config('customerportalapi.otp_email_enabled', true) || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new OTPMail($otp, $user->name));
        } catch (\Throwable $exception) {
            Log::warning('Customer portal OTP email could not be sent.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendSms(User $user, string $otp): void
    {
        $webhookUrl = trim((string) config('customerportalapi.otp_sms_webhook_url', ''));
        $phone = trim((string) ($user->responsible_mobile ?: $user->secondary_mobile));

        if ($webhookUrl === '' || $phone === '') {
            return;
        }

        $message = "Your New WorldCargo verification code is {$otp}. It expires in 10 minutes.";
        $request = Http::timeout(10)->acceptJson();
        $token = trim((string) config('customerportalapi.otp_sms_webhook_token', ''));

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post($webhookUrl, [
                'to' => $phone,
                'message' => $message,
                'from' => (string) config('customerportalapi.otp_sms_from', 'New WorldCargo'),
                'purpose' => 'customer_portal_verification',
                'userId' => (string) $user->id,
            ]);

            if (!$response->successful()) {
                Log::warning('Customer portal OTP SMS webhook returned a non-success response.', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Customer portal OTP SMS could not be sent.', [
                'user_id' => $user->id,
                'phone' => $phone,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

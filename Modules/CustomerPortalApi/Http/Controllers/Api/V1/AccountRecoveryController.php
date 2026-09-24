<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use App\Mail\OTPMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Crypt, DB, Hash, Mail, Validator};
use Illuminate\Support\Str;
use Modules\CustomerPortalApi\Services\Portal\RegistrationPhone;

class AccountRecoveryController extends PortalController
{
    public function start(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:160', 'phone' => 'required|string|max:30',
            'email' => 'required|email|max:255', 'shipmentReference' => 'nullable|string|max:100',
            'detail' => 'required|string|min:10|max:2000',
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Check the highlighted details.', 422, $validator->errors()->toArray());
        $phone = RegistrationPhone::normalise($request->input('phone'));
        if (!$phone) return $this->problem($request, 'VALIDATION_FAILED', 'Check your phone number.', 422, ['phone' => ['Enter one phone number with its country code.']]);
        $email = strtolower(trim($request->input('email')));
        $limit = 'portal-recovery-email:'.hash('sha256', $email);
        if (!Cache::add($limit, true, 60)) return $this->problem($request, 'RATE_LIMITED', 'Wait a minute before requesting another code.', 429);
        $reference = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        $details = array_merge($validator->validated(), ['email' => $email, 'phone' => $phone]);
        Cache::put('portal-account-claim:'.$reference, [
            'details' => Crypt::encryptString(json_encode($details, JSON_THROW_ON_ERROR)),
            'hash' => Hash::make($code), 'attempts' => 0, 'expires' => now()->addMinutes(10)->timestamp,
        ], 600);
        try {
            Mail::to($email)->send((new OTPMail($code, $details['name']))->subject('Confirm your account recovery email'));
        } catch (\Throwable $exception) {
            Cache::forget('portal-account-claim:'.$reference);
            return $this->problem($request, 'EMAIL_UNAVAILABLE', 'We could not send your email code. Please try again shortly.', 503, [], true);
        }
        return $this->success($request, ['reference' => $reference, 'expiresIn' => 600]);
    }

    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), ['reference' => 'required|uuid', 'code' => 'required|digits:6']);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Enter the six-digit email code.', 422, $validator->errors()->toArray());
        $reference = $request->input('reference');
        $lock = Cache::lock('portal-account-claim-lock:'.$reference, 15);
        if (!$lock->get()) return $this->problem($request, 'RECOVERY_BUSY', 'Please wait a moment and try again.', 409);
        try {
            $key = 'portal-account-claim:'.$reference;
            $challenge = Cache::get($key);
            if (!$challenge || $challenge['expires'] < now()->timestamp) return $this->problem($request, 'OTP_EXPIRED', 'Your code has expired. Request a new code.', 422);
            if ($challenge['attempts'] >= 5) return $this->problem($request, 'OTP_ATTEMPTS_EXCEEDED', 'Too many attempts. Request a new code.', 429);
            if (!Hash::check($request->input('code'), $challenge['hash'])) {
                $challenge['attempts']++;
                Cache::put($key, $challenge, max(1, $challenge['expires'] - now()->timestamp));
                return $this->problem($request, 'OTP_INVALID', 'That code is not correct. Check your email and try again.', 422);
            }
            // Verifying a reachable email proves contactability, not ownership of any account.
            DB::table('customer_account_recovery_requests')->insertOrIgnore([
                'reference' => $reference, 'encrypted_details' => $challenge['details'], 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            // Keep the challenge until expiry so a lost response can be retried
            // with the same proof; the unique reference prevents duplicate reviews.
            return $this->success($request, ['reference' => $reference, 'status' => 'pending_review'], 201);
        } finally {
            $lock->release();
        }
    }
}

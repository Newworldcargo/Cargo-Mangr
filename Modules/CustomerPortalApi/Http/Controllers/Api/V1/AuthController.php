<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use App\Models\User;
use App\Notifications\PasswordResetRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\Cargo\Entities\Client;
use Modules\CustomerPortalApi\Http\Resources\AuthUserResource;
use Modules\CustomerPortalApi\Services\Portal\PortalBffService;

class AuthController extends PortalController
{
    public function csrf(Request $request)
    {
        if (!$request->session()->token()) {
            $request->session()->regenerateToken();
        }

        return $this->success($request, null);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $identifier = trim($request->input('identifier'));
        $user = User::where('email', $identifier)
            ->orWhere('responsible_mobile', $identifier)
            ->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return $this->problem($request, 'UNAUTHENTICATED', 'The supplied credentials are invalid.', 401);
        }

        $client = app(\Modules\CustomerPortalApi\Services\Portal\PortalCustomerAccess::class)->clientFor($user);
        if (!$client) {
            return $this->problem($request, 'FORBIDDEN', 'This account is not enabled for the customer portal.', 403);
        }

        if (!(bool) $user->verified) {
            return $this->problem($request, 'CONTACT_UNVERIFIED', 'Verify your contact before signing in.', 403);
        }

        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $user->setRelation('portalClient', $client);

        $response = $this->success($request, (new AuthUserResource($user))->resolve($request));
        return $this->withBffSessionHeaders($request, $response, $user);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstName' => ['required', 'string', 'min:2', 'max:80'],
            'lastName' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $user = DB::transaction(function () use ($request) {
            $name = trim($request->input('firstName') . ' ' . $request->input('lastName', ''));
            $user = new User();
            $user->name = $name;
            $user->email = strtolower(trim($request->input('email')));
            $user->password = Hash::make($request->input('password'));
            $user->responsible_mobile = trim($request->input('phone'));
            $user->role = 4;
            $user->verified = false;
            $user->otp = random_int(100000, 999999);
            $user->otp_expires_at = now()->addMinutes(10);
            $user->save();

            $client = new Client();
            $client->code = 0;
            $client->user_id = $user->id;
            $client->name = $name;
            $client->email = $user->email;
            $client->responsible_name = $name;
            $client->responsible_mobile = $user->responsible_mobile;
            $client->is_archived = 0;
            $client->created_by = $user->id;
            $client->save();
            $client->code = $client->id;
            $client->save();

            return $user->fresh();
        });

        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $user->setRelation('portalClient', Client::where('user_id', $user->id)->first());

        $response = $this->success($request, (new AuthUserResource($user))->resolve($request), 201);
        return $this->withBffSessionHeaders($request, $response, $user);
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please enter the six-digit verification code.', 422, $validator->errors()->toArray());
        }

        $user = Auth::guard('web')->user();
        if (!$user) {
            return $this->problem($request, 'UNAUTHENTICATED', 'Your verification session has expired.', 401);
        }

        if ((string) $user->otp !== (string) $request->input('code')) {
            return $this->problem($request, 'OTP_INVALID', 'The verification code is invalid.', 422);
        }

        if ($user->otp_expires_at && now()->greaterThan($user->otp_expires_at)) {
            return $this->problem($request, 'OTP_EXPIRED', 'The verification code has expired.', 422);
        }

        $user->verified = true;
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();
        $request->session()->regenerateToken();

        return $this->success($request, null);
    }

    public function resendVerification(Request $request)
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return $this->problem($request, 'UNAUTHENTICATED', 'Your verification session has expired.', 401);
        }
        if ((bool) $user->verified) {
            return $this->success($request, null);
        }

        $user->otp = random_int(100000, 999999);
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();
        return $this->success($request, null);
    }

    public function requestPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), ['email' => ['required', 'email', 'max:255']]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Enter a valid email address.', 422, $validator->errors()->toArray());

        $user = User::where('email', strtolower(trim((string) $request->input('email'))))->first();
        $client = $user ? app(\Modules\CustomerPortalApi\Services\Portal\PortalCustomerAccess::class)->clientFor($user) : null;
        if ($user && $client) {
            $user->notify(new PasswordResetRequest(Password::broker()->createToken($user)));
        }

        return $this->success($request, null);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the password fields.', 422, $validator->errors()->toArray());

        $status = Password::reset($request->only('email', 'token', 'password', 'password_confirmation'), function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) return $this->problem($request, 'PASSWORD_RESET_INVALID', 'This reset link is invalid or has expired. Request a new one.', 422);
        return $this->success($request, null);
    }

    public function verifyPassword(Request $request)
    {
        $validator = Validator::make($request->all(), ['password' => ['required', 'string']]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'A password is required.', 422, $validator->errors()->toArray());
        $user = Auth::guard('web')->user();
        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return $this->problem($request, 'CURRENT_PASSWORD_INVALID', 'The current password is invalid.', 422);
        }
        $request->session()->put('portal_recent_auth_at', now()->timestamp);
        return $this->success($request, null);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => ['required', 'string'],
            'nextPassword' => ['required', 'string', 'min:8'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the password fields.', 422, $validator->errors()->toArray());
        $user = Auth::guard('web')->user();
        if (!$user || !Hash::check($request->input('currentPassword'), $user->password)) {
            return $this->problem($request, 'CURRENT_PASSWORD_INVALID', 'The current password is invalid.', 422);
        }
        $user->password = Hash::make($request->input('nextPassword'));
        $user->save();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return $this->success($request, null);
    }

    private function withBffSessionHeaders(Request $request, $response, $user)
    {
        $bff = app(PortalBffService::class);
        if (!$bff->configured() || !$bff->authorizeServiceRequest($request)) {
            return $response;
        }

        $issued = $bff->issueForUser($user, $request);
        return $response->withHeaders([
            'X-NWC-BFF-Session' => $issued['token'],
            'X-NWC-BFF-CSRF-Token' => $issued['csrf'],
            'Cache-Control' => 'no-store',
        ]);
    }

    public function logout(Request $request)
    {
        app(PortalBffService::class)->revokeAssertion($request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent(204)->withHeaders([
            'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
        ]);
    }
}

<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\CustomerPortalApi\Models\PortalBffSession;

class PortalBffService
{
    public function configured()
    {
        return (string) config('customerportalapi.bff_service_token', '') !== ''
            && (string) config('customerportalapi.bff_shared_secret', '') !== '';
    }

    public function isBffRequest(Request $request)
    {
        return $this->configured()
            && $this->authorizeServiceRequest($request)
            && (string) $request->header('X-NWC-Customer-Assertion', '') !== '';
    }

    public function authorizeServiceRequest(Request $request)
    {
        $configuredToken = (string) config('customerportalapi.bff_service_token', '');
        $provided = (string) $request->bearerToken();

        return $configuredToken !== '' && $provided !== '' && hash_equals($configuredToken, $provided);
    }

    public function issueForUser($user, Request $request)
    {
        $rawToken = Str::random(96);
        $rawCsrf = $this->deriveCsrf($rawToken);
        $session = PortalBffSession::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'csrf_hash' => hash('sha256', $rawCsrf),
            'expires_at' => now()->addHours((int) config('customerportalapi.bff_session_hours', 8)),
            'created_ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return [
            'token' => $rawToken,
            'csrf' => $rawCsrf,
            'session' => $session,
        ];
    }

    public function exchange(Request $request, $rawToken)
    {
        if (!$this->authorizeServiceRequest($request) || !$this->configured() || !is_string($rawToken) || $rawToken === '') {
            return null;
        }

        $session = PortalBffSession::where('token_hash', hash('sha256', $rawToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return null;
        }

        $session->forceFill(['last_used_at' => now()])->save();
        $user = $session->user_id ? $session->user : null;
        if (!app(PortalCustomerAccess::class)->canAccess($user)) {
            return null;
        }

        return [
            'session' => $session,
            'user' => $user,
            'assertion' => $this->makeAssertion($session, $user),
            'csrf' => $this->deriveCsrf($rawToken),
        ];
    }

    public function authenticateAssertion(Request $request)
    {
        if (!$this->authorizeServiceRequest($request) || !$this->configured()) {
            return null;
        }

        $assertion = (string) $request->header('X-NWC-Customer-Assertion', '');
        $parts = explode('.', $assertion);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, config('customerportalapi.bff_shared_secret'), true));
        if (!hash_equals($expected, $encodedSignature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload) || empty($payload['sid']) || empty($payload['uid']) || empty($payload['exp']) || (int) $payload['exp'] < time()) {
            return null;
        }

        $session = PortalBffSession::whereKey((int) $payload['sid'])
            ->where('user_id', (int) $payload['uid'])
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        if (!$session) {
            return null;
        }

        $session->forceFill(['last_used_at' => now()])->save();
        $user = $session->user;
        return app(PortalCustomerAccess::class)->canAccess($user) ? $user : null;
    }

    public function revokeAssertion(Request $request)
    {
        if (!$this->authenticateAssertion($request)) {
            return false;
        }

        $assertion = (string) $request->header('X-NWC-Customer-Assertion', '');
        $parts = explode('.', $assertion);
        if (count($parts) !== 2) {
            return false;
        }

        $payload = json_decode($this->base64UrlDecode($parts[0]), true);
        if (!is_array($payload) || empty($payload['sid'])) {
            return false;
        }

        return PortalBffSession::whereKey((int) $payload['sid'])->update(['revoked_at' => now()]) > 0;
    }

    private function makeAssertion(PortalBffSession $session, $user)
    {
        $payload = $this->base64UrlEncode(json_encode([
            'sid' => (int) $session->id,
            'uid' => (int) $user->id,
            'exp' => min($session->expires_at->timestamp, time() + 300),
        ], JSON_UNESCAPED_SLASHES));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $payload, config('customerportalapi.bff_shared_secret'), true));
        return $payload . '.' . $signature;
    }

    public function validBffCsrf(Request $request)
    {
        $provided = (string) $request->header(config('customerportalapi.csrf_header', 'X-CSRF-Token'), '');
        if ($provided === '' || !$this->authorizeServiceRequest($request)) {
            return false;
        }

        $assertion = (string) $request->header('X-NWC-Customer-Assertion', '');
        $parts = explode('.', $assertion);
        if (count($parts) !== 2) {
            return false;
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, config('customerportalapi.bff_shared_secret'), true));
        if (!hash_equals($expected, $encodedSignature)) {
            return false;
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload) || empty($payload['sid'])) {
            return false;
        }

        $session = PortalBffSession::whereKey((int) $payload['sid'])
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        return $session && hash_equals((string) $session->csrf_hash, hash('sha256', $provided));
    }

    private function deriveCsrf($rawToken)
    {
        return $this->base64UrlEncode(hash_hmac('sha256', 'csrf|' . $rawToken, config('customerportalapi.bff_shared_secret'), true));
    }

    private function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode($value)
    {
        return base64_decode(strtr($value . str_repeat('=', (4 - strlen($value) % 4) % 4), '-_', '+/'), true) ?: '';
    }
}

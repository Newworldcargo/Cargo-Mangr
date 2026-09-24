<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use App\Mail\OTPMail;
use App\Models\User;
use Illuminate\Support\Facades\{Cache, Hash, Mail};

class PortalPasswordChallenge
{
    public function send(User $user): void
    {
        if (!$user->verified || !filter_var($user->email, FILTER_VALIDATE_EMAIL) || str_ends_with(strtolower($user->email), '.invalid')) return;
        $key = 'portal-password-challenge:'.$user->id;
        if (!Cache::add($key.':cooldown', true, 60)) return;
        $code = (string) random_int(100000, 999999);
        Cache::put($key, ['hash' => Hash::make($code), 'attempts' => 0, 'expires' => now()->addMinutes(10)->timestamp], 600);
        try {
            // Historic phone contacts are not proof of phone ownership. Password
            // recovery uses the account email; account claiming is reviewed separately.
            Mail::to($user->email)->send(new OTPMail($code, $user->name));
        } catch (\Throwable $exception) {
            Cache::forget($key);
            throw $exception;
        }
    }

    public function consume(User $user, string $code): bool
    {
        $key = 'portal-password-challenge:'.$user->id;
        $challenge = Cache::get($key);
        if (!$challenge || $challenge['expires'] < now()->timestamp || $challenge['attempts'] >= 5) return false;
        if (!Hash::check($code, $challenge['hash'])) {
            $challenge['attempts']++;
            Cache::put($key, $challenge, max(1, $challenge['expires'] - now()->timestamp));
            return false;
        }
        Cache::forget($key);
        return true;
    }
}

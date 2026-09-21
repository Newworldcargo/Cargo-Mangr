<?php
namespace App\Services\Messaging;

use App\Models\MessagingSetting;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class MtnClient
{
    public function authenticate(MessagingSetting $settings): string
    {
        if (!$settings->email || !$settings->password) throw new RejectedSend('MTN account credentials are missing.');
        $cacheKey = 'mtn-token:' . hash('sha256', $settings->email . $settings->password);
        try {
            $token = Cache::lock($cacheKey . ':lock', 20)->block(2, function () use ($cacheKey, $settings) {
                if (Cache::has($cacheKey . ':blocked')) throw new RejectedSend('MTN authentication needs review before further login attempts.');
                if (Cache::has($cacheKey . ':cooldown')) throw new RetryableSend('MTN authentication is cooling down.');
                if ($cached = Cache::get($cacheKey)) return Crypt::decryptString($cached);
                $response = Http::acceptJson()->withOptions(['connect_timeout' => 3])->timeout(10)->post(config('messaging.base_url') . config('messaging.login_path'), ['email' => $settings->email, 'password' => $settings->password]);
                if (in_array($response->status(), [400, 401, 403], true)) {
                    Cache::put($cacheKey . ':blocked', true, 900);
                    throw new RejectedSend('MTN authentication was rejected.');
                }
                if (!$response->successful() || !$response->json('access_token')) {
                    Cache::put($cacheKey . ':cooldown', true, 60);
                    throw new RetryableSend('MTN authentication is temporarily unavailable.');
                }
                $token = $response->json('access_token');
                $parts = explode('.', $token);
                $claims = json_decode(base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true) ?: [];
                $ttl = max(1, min(300, ($claims['exp'] ?? time() + 300) - time() - 30));
                Cache::put($cacheKey, Crypt::encryptString($token), $ttl);
                return $token;
            });
        } catch (RejectedSend | RetryableSend $error) { throw $error; }
        catch (\Throwable $error) {
            Cache::put($cacheKey . ':cooldown', true, 30);
            throw new RetryableSend('MTN authentication is temporarily unavailable.');
        }
        return $token;
    }

    public function send(OutboundMessage $message, MessagingSetting $settings): string
    {
        $sender = $message->purpose === 'otp' ? $settings->sender_otp : $settings->sender_txn;
        if (!$sender) throw new RejectedSend('MTN approved sender is missing.');
        $token = $this->authenticate($settings);
        $cacheKey = 'mtn-token:' . hash('sha256', $settings->email . $settings->password);

        // Never retry an ambiguous send automatically: MTN has not confirmed idempotency.
        $response = Http::withToken($token)->acceptJson()->withOptions(['connect_timeout' => 3])->timeout(15)
            ->post(config('messaging.base_url') . config('messaging.send_path'), [
                'msg' => $message->content['body'], 'recipient' => $message->recipient,
                'sender' => $sender, 'category' => $message->purpose === 'otp' ? 'OTP' : 'TXN',
                'clientTxnId' => 'nwc-' . $message->id,
            ]);
        if ($response->status() === 401) {
            Cache::forget($cacheKey);
            throw new RetryableSend('MTN authentication expired.');
        }
        if ($response->status() === 429) throw new RetryableSend('MTN rate limit reached.');
        if ($response->clientError()) throw new RejectedSend('MTN rejected this message (HTTP ' . $response->status() . ').');
        if (!$response->successful() || (string) $response->json('statusCode') !== '0' || !$response->json('txnId')) {
            throw new \RuntimeException('MTN acceptance could not be confirmed.');
        }
        return (string) $response->json('txnId');
    }
}

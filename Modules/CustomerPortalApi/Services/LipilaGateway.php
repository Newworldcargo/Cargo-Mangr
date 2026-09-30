<?php

namespace Modules\CustomerPortalApi\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LipilaGateway
{
    public function ready(): bool
    {
        return (bool) config('lipila.enabled') && $this->configured();
    }

    public function configured(): bool
    {
        return in_array(rtrim((string) config('lipila.base_url'), '/'), ['https://api.lipila.dev', 'https://api.lipila.io', 'https://blz.lipila.io'], true)
            && trim((string) config('lipila.secret_key')) !== ''
            && strlen((string) base64_decode((string) config('lipila.webhook_secret'), true)) === 32;
    }

    private function http()
    {
        if (!$this->configured()) throw new \RuntimeException('Lipila is not configured.');
        $url = rtrim((string) config('lipila.base_url'), '/');
        if (!in_array($url, ['https://api.lipila.dev', 'https://api.lipila.io', 'https://blz.lipila.io'], true)) {
            throw new \RuntimeException('Invalid Lipila endpoint.');
        }
        return Http::baseUrl($url)->acceptJson()->asJson()->timeout(20)
            ->withOptions(['allow_redirects' => false, 'connect_timeout' => 5])
            ->withHeaders(['x-api-key' => config('lipila.secret_key')]);
    }

    public function collect(array $payload, ?array $customer = null): array
    {
        if (!$this->ready()) throw new \RuntimeException('New Lipila collections are disabled.');
        $body = $customer ? ['customerInfo' => $customer, 'collectionRequest' => $payload + ['backUrl' => config('lipila.return_url')]] : $payload;
        // Never retry collection POSTs: a timeout can happen after the wallet was charged.
        $response = $this->http()->withHeaders(['callbackUrl' => config('lipila.callback_url')])
            ->post('/api/v1/collections/' . ($customer ? 'card' : 'mobile-money'), $body);
        return ['accepted' => $response->successful(), 'data' => is_array($response->json()) ? $response->json() : []];
    }

    public function status(string $reference): ?array
    {
        $response = $this->http()->get('/api/v1/collections/check-status', ['referenceId' => $reference]);
        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    public function checkoutUrl($url): ?string
    {
        if (!is_string($url)) return null;
        $parts = parse_url($url);
        return $parts && ($parts['scheme'] ?? '') === 'https' && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['port']) && in_array($parts['host'] ?? '', config('lipila.checkout_hosts', []), true) ? $url : null;
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = base64_decode((string) config('lipila.webhook_secret'), true);
        $id = (string) $request->header('webhook-id');
        $timestamp = (string) $request->header('webhook-timestamp');
        if (!$secret || strlen($secret) !== 32 || $id === '' || !ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300) return false;
        $expected = 'v1,' . base64_encode(hash_hmac('sha256', $id . '.' . $timestamp . '.' . $request->getContent(), $secret, true));
        foreach (explode(' ', (string) $request->header('webhook-signature')) as $signature) {
            if (hash_equals($expected, trim($signature))) return true;
        }
        return false;
    }
}

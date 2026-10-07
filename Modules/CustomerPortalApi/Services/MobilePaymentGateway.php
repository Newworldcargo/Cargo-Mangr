<?php

namespace Modules\CustomerPortalApi\Services;

use Illuminate\Support\Facades\Http;

class MobilePaymentGateway
{
    public function enabled(): bool
    {
        $provider = trim((string) config('customerportalapi.payment_provider', ''));
        return ($provider === 'local-uat' && app()->environment(['local', 'testing']))
            || ($provider !== '' && trim((string) config('customerportalapi.payment_webhook_url', '')) !== '');
    }

    public function create(array $payload): array
    {
        $provider = (string) config('customerportalapi.payment_provider');
        if ($provider === 'local-uat' && app()->environment(['local', 'testing'])) {
            return ['status' => 'succeeded', 'providerReference' => 'LOCAL-UAT-' . $payload['intentId'], 'clientToken' => null];
        }
        $url = trim((string) config('customerportalapi.payment_webhook_url', ''));
        if ($url === '') throw new \RuntimeException('Payment processing is not configured.');
        return $this->request()->post($url, array_merge($payload, ['provider' => $provider]))->throw()->json();
    }

    public function status(string $intentId, ?string $reference): ?array
    {
        $url = trim((string) config('customerportalapi.payment_status_url', ''));
        if ($url === '') return null;
        return $this->request()->get($url, ['intentId' => $intentId, 'providerReference' => $reference])->throw()->json();
    }

    public function successful(string $status): bool
    {
        return in_array($status, ['succeeded', 'confirmed', 'completed'], true);
    }

    public function failed(string $status): bool
    {
        return in_array($status, ['failed', 'cancelled', 'declined', 'expired'], true);
    }

    private function request()
    {
        $request = Http::timeout(15)->acceptJson();
        $token = trim((string) config('customerportalapi.payment_webhook_token', ''));
        return $token !== '' ? $request->withToken($token) : $request;
    }
}

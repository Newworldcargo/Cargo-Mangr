<?php

namespace Modules\CustomerPortalApi\Services\Pricing;

use InvalidArgumentException;
use Modules\CustomerPortalApi\Models\PortalQuote;

class MobileBookingQuoteSigner
{
    public function sign(array $payload): string
    {
        return hash_hmac('sha256', $this->encode($payload), $this->key());
    }

    public function requestHash(array $request): string
    {
        return hash('sha256', $this->encode($request));
    }

    public function requireValidQuote(array $pricing, int $clientId, string $service): PortalQuote
    {
        $payload = (array) ($pricing['quotePayload'] ?? []);
        $signature = (string) ($pricing['quoteSignature'] ?? '');

        if (($pricing['quoteSource'] ?? null) !== 'server' || $payload === [] || $signature === '') {
            throw new InvalidArgumentException('A signed server quote is required.');
        }
        if (!hash_equals($this->sign($payload), $signature)) {
            throw new InvalidArgumentException('The quote signature is invalid.');
        }
        if ((string) ($payload['customerId'] ?? '') !== (string) $clientId || (string) ($payload['service'] ?? '') !== $service) {
            throw new InvalidArgumentException('The quote does not belong to this booking.');
        }
        if ($this->requestHash((array) ($pricing['request'] ?? [])) !== (string) ($payload['requestHash'] ?? '')) {
            throw new InvalidArgumentException('The booking details changed after the quote was issued.');
        }

        $quote = PortalQuote::whereKey($payload['quoteId'] ?? null)
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->first();
        if (!$quote || !$quote->expires_at || $quote->expires_at->isPast()) {
            throw new InvalidArgumentException('The quote is unavailable or expired.');
        }
        if ((int) $quote->amount_minor !== (int) round(((float) ($payload['total'] ?? -1)) * 100)) {
            throw new InvalidArgumentException('The quote amount does not match the server record.');
        }
        if (strtoupper((string) $quote->currency) !== strtoupper((string) ($payload['currency'] ?? ''))) {
            throw new InvalidArgumentException('The quote currency does not match the server record.');
        }

        return $quote;
    }

    private function encode(array $value): string
    {
        return json_encode($this->canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function canonicalize(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }

    private function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: $key;
        }

        return $key;
    }
}

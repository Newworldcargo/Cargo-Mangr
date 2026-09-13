<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\CustomerPortalApi\Models\PortalAccountPreference;
use Modules\CustomerPortalApi\Models\PortalNotificationPreference;
use Modules\CustomerPortalApi\Models\PortalPushToken;

class NotificationPreferenceController extends PortalController
{
    public function show(Request $request)
    {
        return $this->success($request, $this->snapshot());
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipmentUpdates' => ['sometimes', 'boolean'],
            'billUpdates' => ['sometimes', 'boolean'],
            'marketing' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $preference = $this->preference();
        if ($request->has('shipmentUpdates')) {
            $preference->shipment_updates = $request->boolean('shipmentUpdates');
        }
        if ($request->has('billUpdates')) {
            $preference->bill_updates = $request->boolean('billUpdates');
        }
        if ($request->has('marketing')) {
            $preference->marketing = $request->boolean('marketing');
            $this->syncAccountMarketing($preference->marketing);
        }
        $preference->revision = ((int) ($preference->revision ?: 1)) + 1;
        $preference->save();

        return $this->success($request, $this->snapshot());
    }

    public function registerToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:2000'],
            'provider' => ['sometimes', 'nullable', 'string', 'in:expo,apns,fcm'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $client = $this->customerContext->requireClient();
        $user = $this->customerContext->user();
        $token = trim((string) $request->input('token'));

        PortalPushToken::updateOrCreate(
            ['token_hash' => hash('sha256', $token)],
            [
                'client_id' => $client->id,
                'user_id' => $user->id,
                'provider' => $request->input('provider') ?: 'expo',
                'platform' => $request->input('platform'),
                'push_token' => $token,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        return $this->success($request, [
            'registered' => true,
            'provider' => $request->input('provider') ?: 'expo',
        ], 201);
    }

    public function revokeToken(Request $request)
    {
        $client = $this->customerContext->requireClient();
        $token = trim((string) $request->input('token', ''));
        $query = PortalPushToken::where('client_id', $client->id)->whereNull('revoked_at');
        if ($token !== '') {
            $query->where('token_hash', hash('sha256', $token));
        }
        $query->update(['revoked_at' => now()]);

        return response()->noContent(204)->withHeaders([
            'X-Request-ID' => (string) $request->attributes->get('portal_request_id'),
        ]);
    }

    private function snapshot()
    {
        $preference = $this->preference();

        return [
            'shipmentUpdates' => (bool) $preference->shipment_updates,
            'billUpdates' => (bool) $preference->bill_updates,
            'marketing' => (bool) $preference->marketing,
            'pushRegistered' => PortalPushToken::where('client_id', $preference->client_id)->whereNull('revoked_at')->exists(),
            'revision' => (int) ($preference->revision ?: 1),
        ];
    }

    private function preference()
    {
        $client = $this->customerContext->requireClient();

        return PortalNotificationPreference::firstOrCreate(
            ['client_id' => $client->id],
            ['shipment_updates' => true, 'bill_updates' => true, 'marketing' => false, 'revision' => 1]
        );
    }

    private function syncAccountMarketing($enabled)
    {
        $client = $this->customerContext->requireClient();
        $account = PortalAccountPreference::firstOrCreate(
            ['client_id' => $client->id],
            ['marketing_enabled' => false, 'revision' => 1]
        );
        $account->marketing_enabled = (bool) $enabled;
        $account->revision = ((int) ($account->revision ?: 1)) + 1;
        $account->save();
    }
}

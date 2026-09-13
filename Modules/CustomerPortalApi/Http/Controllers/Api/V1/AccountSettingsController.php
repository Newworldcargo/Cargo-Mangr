<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\CustomerPortalApi\Models\PortalAccountPreference;
use Modules\CustomerPortalApi\Models\PortalBffSession;

class AccountSettingsController extends PortalController
{
    public function show(Request $request)
    {
        return $this->success($request, $this->snapshot($request));
    }

    public function revokeDevice(Request $request, $device)
    {
        $session = $this->ownedSession($device);
        if (!$session) {
            return $this->problem($request, 'NOT_FOUND', 'Recognized device not found.', 404);
        }

        $session->revoked_at = now();
        $session->save();

        return $this->success($request, $this->snapshot($request));
    }

    public function trustDevice(Request $request, $device)
    {
        $session = $this->ownedSession($device);
        if (!$session) {
            return $this->problem($request, 'NOT_FOUND', 'Recognized device not found.', 404);
        }

        $session->trusted_at = now();
        $session->save();

        return $this->success($request, $this->snapshot($request));
    }

    public function marketing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $preference = $this->preference();
        $preference->marketing_enabled = $request->boolean('enabled');
        $preference->revision = ((int) ($preference->revision ?: 1)) + 1;
        $preference->save();

        return $this->success($request, $this->snapshot($request));
    }

    public function requestDataExport(Request $request)
    {
        $preference = $this->preference();
        $preference->data_export_requested_at = now();
        $preference->revision = ((int) ($preference->revision ?: 1)) + 1;
        $preference->save();

        return $this->success($request, $this->snapshot($request));
    }

    public function requestDeletion(Request $request)
    {
        $preference = $this->preference();
        $preference->deletion_requested_at = now();
        $preference->revision = ((int) ($preference->revision ?: 1)) + 1;
        $preference->save();

        return $this->success($request, $this->snapshot($request));
    }

    private function snapshot(Request $request)
    {
        $client = $this->customerContext->requireClient();
        $user = $this->customerContext->user();
        $preference = $this->preference();

        $currentToken = (string) $request->bearerToken();
        $currentTokenHash = $currentToken !== '' ? hash('sha256', $currentToken) : null;

        $devices = PortalBffSession::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_used_at')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($session) use ($currentTokenHash) {
                $lastSeen = $session->last_used_at ?: $session->updated_at;
                $name = $session->device_label ?: $this->deviceName($session->user_agent);

                return [
                    'id' => (string) $session->id,
                    'name' => $name,
                    'detail' => trim(($lastSeen ? 'Last seen ' . $lastSeen->diffForHumans() : 'Recently used') . ($session->created_ip ? ' · ' . $session->created_ip : '')),
                    'current' => $currentTokenHash && hash_equals((string) $session->token_hash, $currentTokenHash),
                    'trusted' => (bool) $session->trusted_at,
                    'expiresAt' => $session->expires_at ? $session->expires_at->toIso8601String() : null,
                ];
            })
            ->values()
            ->all();

        return [
            'customerId' => (string) $client->id,
            'devices' => $devices,
            'marketingEnabled' => (bool) $preference->marketing_enabled,
            'dataExportRequested' => (bool) $preference->data_export_requested_at,
            'deletionRequested' => (bool) $preference->deletion_requested_at,
            'revision' => (int) ($preference->revision ?: 1),
        ];
    }

    private function preference()
    {
        $client = $this->customerContext->requireClient();

        return PortalAccountPreference::firstOrCreate(
            ['client_id' => $client->id],
            ['marketing_enabled' => false, 'revision' => 1]
        );
    }

    private function ownedSession($id)
    {
        return PortalBffSession::where('user_id', $this->customerContext->user()->id)
            ->whereNull('revoked_at')
            ->whereKey($id)
            ->first();
    }

    private function deviceName($userAgent)
    {
        if (!$userAgent) {
            return 'Recognized device';
        }

        if (stripos($userAgent, 'iphone') !== false || stripos($userAgent, 'ipad') !== false) {
            return 'Apple device';
        }
        if (stripos($userAgent, 'android') !== false) {
            return 'Android device';
        }

        return 'Web or mobile session';
    }
}

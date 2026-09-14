<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TelemetryController extends PortalController
{
    private const EVENTS = [
        'app_opened', 'session_restored', 'login_attempted', 'login_failed',
        'shipment_list_opened', 'tracking_search_submitted', 'booking_draft_created',
        'booking_submitted', 'invoice_opened', 'payment_attempted', 'payment_completed',
        'support_case_submitted', 'api_error_occurred', 'native_permission_denied',
    ];

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event' => ['required', 'string', 'in:' . implode(',', self::EVENTS)],
            'appVersion' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'in:android,ios,web'],
            'properties' => ['nullable', 'array', 'max:20'],
            'properties.*' => ['nullable'],
        ]);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'A valid telemetry event is required.', 422, $validator->errors()->toArray());
        }

        $properties = (array) $request->input('properties', []);
        foreach (array_keys($properties) as $key) {
            if (preg_match('/token|password|secret|authorization|cookie|phone|email|address/i', (string) $key)) {
                return $this->problem($request, 'SENSITIVE_TELEMETRY_REJECTED', 'Sensitive fields are not accepted in telemetry.', 422);
            }
        }

        $safeProperties = collect($properties)->map(function ($value) {
            if (is_bool($value) || is_numeric($value) || $value === null) return $value;
            return mb_substr((string) $value, 0, 240);
        })->all();

        Log::info('mobile.telemetry', [
            'event' => $request->input('event'),
            'app_version' => $request->input('appVersion'),
            'platform' => $request->input('platform'),
            'properties' => $safeProperties,
            'request_id' => $request->attributes->get('portal_request_id'),
        ]);

        return response()->json(null, 202, ['X-Request-ID' => (string) $request->attributes->get('portal_request_id')]);
    }
}

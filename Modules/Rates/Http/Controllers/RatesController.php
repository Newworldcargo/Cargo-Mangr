<?php

namespace Modules\Rates\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\Rates\Services\RatesService;

/**
 * Controller for the Rates module's user-facing actions.
 *
 * - POST /admin/rates/refresh  : one-click server-side rate refresh
 *                                (mirrors the manual "Refresh" in the
 *                                 exchange-rate settings modal)
 * - GET  /admin/rates/status   : last-sync info shown on the settings page
 */
class RatesController extends Controller
{
    /** @var RatesService */
    private $ratesService;

    public function __construct(RatesService $ratesService)
    {
        $this->ratesService = $ratesService;
    }

    public function refresh(Request $request): JsonResponse
    {
        $result = $this->ratesService->sync();

        if ($result['errors']) {
            return response()->json([
                'success' => false,
                'message' => implode(', ', $result['errors']),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'source' => $result['source'],
            'updated' => $result['updated'],
            'message' => 'Exchange rates refreshed from ' . $result['source'],
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'last_sync_at' => Cache::get('rates:last_sync_at'),
            'last_sync_source' => Cache::get('rates:last_sync_source'),
        ]);
    }
}

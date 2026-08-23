<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\CustomerPortalApi\Services\Portal\PortalBffService;

class BffSessionController extends PortalController
{
    public function exchange(Request $request, PortalBffService $bff)
    {
        if (!$bff->configured()) {
            return $this->problem($request, 'BFF_NOT_CONFIGURED', 'The secure portal service connection is not configured.', 503, [], true);
        }

        $exchange = $bff->exchange($request, (string) $request->header('X-NWC-Portal-Session', ''));
        if (!$exchange) {
            return $this->problem($request, 'UNAUTHENTICATED', 'The portal session is invalid or expired.', 401);
        }

        return $this->success($request, null)->withHeaders([
            'X-NWC-Customer-Assertion' => $exchange['assertion'],
            'X-NWC-BFF-CSRF-Token' => $exchange['csrf'],
            'Cache-Control' => 'no-store',
        ]);
    }
}

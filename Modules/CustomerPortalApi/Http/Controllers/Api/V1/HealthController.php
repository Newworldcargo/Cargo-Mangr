<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;

class HealthController extends PortalController
{
    public function health(Request $request)
    {
        return $this->success($request, [
            'status' => 'ok',
            'service' => 'customer-portal-api',
        ]);
    }

    public function ready(Request $request)
    {
        return $this->success($request, [
            'status' => 'ready',
            'service' => 'customer-portal-api',
        ]);
    }
}

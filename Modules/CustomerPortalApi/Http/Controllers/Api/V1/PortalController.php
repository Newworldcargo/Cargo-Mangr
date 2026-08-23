<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\CustomerPortalApi\Services\Portal\CustomerContext;
use Modules\CustomerPortalApi\Services\Portal\PortalResponse;

abstract class PortalController extends Controller
{
    protected $customerContext;

    public function __construct(CustomerContext $customerContext)
    {
        $this->customerContext = $customerContext;
    }

    protected function success(Request $request, $data, $status = 200, array $meta = [])
    {
        return PortalResponse::success($request, $data, $status, $meta);
    }

    protected function problem(Request $request, $code, $message, $status, array $fieldErrors = [], $retryable = false)
    {
        return PortalResponse::problem($request, $code, $message, $status, $fieldErrors, $retryable);
    }
}

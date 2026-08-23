<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Http\Resources\ShipmentResource;

class ShipmentActionController extends PortalController
{
    public function store(Request $request, $shipment)
    {
        $validator = Validator::make($request->all(), ['action' => ['required', 'string', 'max:50']]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'A shipment action is required.', 422, $validator->errors()->toArray());

        $model = Shipment::where('client_id', $this->customerContext->requireClient()->id)
            ->with(['consignment.trackingHistory', 'from_address', 'packages'])
            ->whereKey($shipment)->first();
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Shipment not found.', 404);

        $expected = trim((string) $request->header('If-Match'), '"');
        if ($expected === '' || (int) $expected !== (int) ($model->revision ?: 1)) {
            return $this->problem($request, 'REVISION_CONFLICT', 'The shipment has changed since it was loaded.', 409);
        }

        $action = $request->input('action');
        $allowed = (new ShipmentResource($model))->resolve($request)['allowedActions'] ?? [];
        if (!in_array($action, $allowed, true)) {
            return $this->problem($request, 'INVALID_STATE_TRANSITION', 'This action is not available for the current shipment state.', 409);
        }

        if ($action === 'cancel') {
            $model->status_id = Shipment::CLOSED_STATUS;
            $model->client_status = Shipment::CLIENT_STATUS_RETURNED;
            $model->revision = ((int) ($model->revision ?: 1)) + 1;
            $model->save();
        }

        return $this->success($request, (new ShipmentResource($model->fresh()->load(['consignment.trackingHistory', 'from_address', 'packages'])))->resolve($request));
    }
}

<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Http\Resources\PickupResource;
use Modules\CustomerPortalApi\Models\PortalPickup;

class PickupController extends PortalController
{
    public function current(Request $request)
    {
        $pickup = PortalPickup::where('client_id', $this->customerContext->requireClient()->id)
            ->whereIn('status', ['requested', 'scheduled'])
            ->with('shipment')
            ->orderByDesc('created_at')
            ->first();

        return $this->success($request, $pickup ? (new PickupResource($pickup))->resolve($request) : null);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipmentId' => ['sometimes', 'nullable', 'integer'],
            'collectionPoint' => ['required', 'string', 'max:500'],
            'scheduledDate' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'scheduledTime' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $client = $this->customerContext->requireClient();
        $shipmentId = $request->input('shipmentId');
        if ($shipmentId !== null && !Shipment::where('client_id', $client->id)->whereKey($shipmentId)->exists()) {
            return $this->problem($request, 'NOT_FOUND', 'Shipment not found.', 404);
        }

        $pickup = PortalPickup::create([
            'client_id' => $client->id,
            'shipment_id' => $shipmentId,
            'status' => 'requested',
            'collection_point' => $request->input('collectionPoint'),
            'scheduled_date' => $request->input('scheduledDate'),
            'scheduled_time' => $request->input('scheduledTime'),
            'revision' => 1,
        ]);

        return $this->success($request, (new PickupResource($pickup))->resolve($request), 201);
    }

    public function cancel(Request $request, $pickup)
    {
        $model = PortalPickup::where('client_id', $this->customerContext->requireClient()->id)->whereKey($pickup)->first();
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Pickup not found.', 404);
        }
        if ($this->revisionConflict($request, $model)) {
            return $this->problem($request, 'REVISION_CONFLICT', 'The pickup has changed since it was loaded.', 409);
        }
        if (!in_array($model->status, ['requested', 'scheduled'], true)) {
            return $this->problem($request, 'INVALID_STATE_TRANSITION', 'This pickup can no longer be cancelled.', 409);
        }
        $model->status = 'cancelled';
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();

        return $this->success($request, (new PickupResource($model->fresh()))->resolve($request));
    }

    private function revisionConflict(Request $request, $model)
    {
        $expected = $request->header('If-Match');
        return $expected !== null && $expected !== '' && (int) trim($expected, '"') !== (int) ($model->revision ?: 1);
    }
}

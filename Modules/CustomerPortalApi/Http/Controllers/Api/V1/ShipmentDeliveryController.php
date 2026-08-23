<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Cargo\Entities\Shipment;

class ShipmentDeliveryController extends PortalController
{
    public function show(Request $request, $shipment)
    {
        $model = $this->owned($shipment);
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Shipment not found.', 404);

        return $this->success($request, [
            'shipmentId' => (string) $model->id,
            'recipientName' => $model->reciver_name,
            'recipientPhone' => $model->reciver_phone,
            'recipientAddress' => $model->reciver_address,
            'revision' => (int) ($model->revision ?: 1),
        ]);
    }

    public function update(Request $request, $shipment)
    {
        $validator = Validator::make($request->all(), [
            'recipientName' => ['sometimes', 'required', 'string', 'max:255'],
            'recipientPhone' => ['sometimes', 'required', 'string', 'max:50'],
            'recipientAddress' => ['sometimes', 'required', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the delivery fields.', 422, $validator->errors()->toArray());

        $model = $this->owned($shipment);
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Shipment not found.', 404);
        $expected = trim((string) $request->header('If-Match'), '"');
        if ($expected !== '' && (int) $expected !== (int) ($model->revision ?: 1)) return $this->problem($request, 'REVISION_CONFLICT', 'The shipment has changed since it was loaded.', 409);
        if (!in_array((int) $model->status_id, [Shipment::SAVED_STATUS, Shipment::REQUESTED_STATUS, Shipment::APPROVED_STATUS, Shipment::IN_STOCK_STATUS], true)) {
            return $this->problem($request, 'INVALID_STATE_TRANSITION', 'Delivery details cannot be changed in the current shipment state.', 409);
        }

        foreach (['recipientName' => 'reciver_name', 'recipientPhone' => 'reciver_phone', 'recipientAddress' => 'reciver_address'] as $input => $column) {
            if ($request->has($input)) $model->{$column} = $request->input($input);
        }
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();
        return $this->show($request, $model->id);
    }

    public function proofOfDelivery(Request $request, $shipment)
    {
        $model = $this->owned($shipment);
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Shipment not found.', 404);
        if ((int) $model->status_id !== Shipment::DELIVERED_STATUS && !$model->received_at) {
            return $this->success($request, null);
        }

        return $this->success($request, [
            'shipmentId' => (string) $model->id,
            'recipientName' => $model->reciver_name,
            'occurredAt' => $model->received_at ? $model->received_at->toIso8601String() : null,
            'method' => $model->condition ? 'recorded' : null,
            'evidenceUrl' => null,
        ]);
    }

    private function owned($id)
    {
        return Shipment::where('client_id', $this->customerContext->requireClient()->id)->whereKey($id)->first();
    }
}

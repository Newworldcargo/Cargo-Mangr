<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Http\Resources\ReturnRequestResource;
use Modules\CustomerPortalApi\Models\PortalReturnRequest;

class ReturnController extends PortalController
{
    public function index(Request $request)
    {
        $client = $this->customerContext->requireClient();
        $returns = PortalReturnRequest::where('client_id', $client->id)
            ->with('shipment')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return $this->success($request, $returns->map(function ($return) use ($request) {
            return (new ReturnRequestResource($return))->resolve($request);
        })->values()->all());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipmentId' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:5000'],
            'handover' => ['required', 'in:pickup,drop_off'],
        ]);
        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $client = $this->customerContext->requireClient();
        $shipment = Shipment::where('client_id', $client->id)->whereKey($request->input('shipmentId'))->first();
        if (!$shipment) {
            return $this->problem($request, 'NOT_FOUND', 'Shipment not found.', 404);
        }
        if (!in_array((int) $shipment->status_id, [Shipment::DELIVERED_STATUS, Shipment::CLOSED_STATUS], true)) {
            return $this->problem($request, 'RETURN_NOT_ELIGIBLE', 'This shipment is not eligible for a return request.', 422);
        }

        $existing = PortalReturnRequest::where('client_id', $client->id)
            ->where('shipment_id', $shipment->id)
            ->whereIn('status', ['requested', 'approved', 'in_transit'])
            ->first();
        if ($existing) {
            return $this->success($request, (new ReturnRequestResource($existing->load('shipment')))->resolve($request));
        }

        $return = PortalReturnRequest::create([
            'client_id' => $client->id,
            'shipment_id' => $shipment->id,
            'reason' => $request->input('reason'),
            'handover' => $request->input('handover'),
            'status' => 'requested',
            'display_status' => 'Requested',
            'revision' => 1,
        ]);

        return $this->success($request, (new ReturnRequestResource($return->load('shipment')))->resolve($request), 201);
    }

    public function show(Request $request, $return)
    {
        $model = $this->owned($return);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Return request not found.', 404);
        }
        return $this->success($request, (new ReturnRequestResource($model->load('shipment')))->resolve($request));
    }

    public function cancel(Request $request, $return)
    {
        $model = $this->owned($return);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Return request not found.', 404);
        }
        if ($this->revisionConflict($request, $model)) {
            return $this->problem($request, 'REVISION_CONFLICT', 'The return request has changed since it was loaded.', 409);
        }
        if (!in_array($model->status, ['requested'], true)) {
            return $this->problem($request, 'INVALID_STATE_TRANSITION', 'This return request can no longer be cancelled.', 409);
        }
        $model->status = 'cancelled';
        $model->display_status = 'Cancelled';
        $model->revision = ((int) ($model->revision ?: 1)) + 1;
        $model->save();
        return $this->success($request, (new ReturnRequestResource($model->fresh()->load('shipment')))->resolve($request));
    }

    private function owned($id)
    {
        return PortalReturnRequest::where('client_id', $this->customerContext->requireClient()->id)->whereKey($id)->first();
    }

    private function revisionConflict(Request $request, $model)
    {
        $expected = $request->header('If-Match');
        return $expected !== null && $expected !== '' && (int) trim($expected, '"') !== (int) ($model->revision ?: 1);
    }
}

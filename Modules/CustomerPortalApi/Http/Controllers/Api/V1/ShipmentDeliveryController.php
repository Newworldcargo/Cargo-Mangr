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

    public function proofOfDeliveryDocument(Request $request, $shipment)
    {
        $model = $this->owned($shipment);
        if (!$model) return $this->problem($request, 'NOT_FOUND', 'Shipment not found.', 404);
        if ((int) $model->status_id !== Shipment::DELIVERED_STATUS && !$model->received_at) {
            return $this->problem($request, 'PROOF_NOT_READY', 'Proof of delivery is available after delivery is confirmed.', 409);
        }

        $reference = $model->code ?: ('shipment-' . $model->id);
        $deliveredAt = $model->received_at ? $model->received_at->format('M j, Y g:i A') : 'Delivery confirmed';
        $content = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Proof of delivery ' . e($reference) . '</title><style>body{margin:0;background:#f5f7f7;color:#012642;font-family:Arial,sans-serif}.page{max-width:680px;margin:32px auto;background:#fff;padding:38px;border-radius:20px;box-sizing:border-box}.brand{font-size:22px;font-weight:800}.badge{display:inline-block;margin-top:12px;padding:7px 11px;border-radius:999px;background:#dff3e8;color:#147145;font-size:12px;font-weight:700}.muted{color:#56707d;line-height:1.5}.route{margin:25px 0;padding:18px;border-radius:16px;background:#edf4f7;font-weight:700}.box{padding:16px;border-radius:16px;background:#f0f8f4;border:1px solid #cfe9db}.foot{margin-top:32px;padding-top:18px;border-top:1px solid #e3e9eb;color:#56707d;font-size:12px;line-height:1.5}</style></head><body><main class="page"><div class="brand">NEW WORLDCARGO</div><div class="badge">DELIVERED AND CONFIRMED</div><h1>Proof of delivery</h1><p class="muted">Tracking ' . e($reference) . '<br>Delivered ' . e($deliveredAt) . '</p><div class="route">' . e((string) $model->client_address) . ' → ' . e((string) $model->reciver_address) . '</div><section class="box"><strong>Recipient</strong><p class="muted">' . e((string) $model->reciver_name) . '<br>' . e((string) $model->reciver_phone) . '</p></section><div class="foot">Generated by New WorldCargo from the customer portal shipment record.</div></main></body></html>';

        return $this->success($request, [
            'filename' => 'new-worldcargo-proof-' . strtolower(preg_replace('/[^A-Za-z0-9\-]+/', '-', $reference)) . '.html',
            'mimeType' => 'text/html;charset=utf-8',
            'content' => $content,
        ]);
    }

    private function owned($id)
    {
        return Shipment::where('client_id', $this->customerContext->requireClient()->id)->whereKey($id)->first();
    }
}

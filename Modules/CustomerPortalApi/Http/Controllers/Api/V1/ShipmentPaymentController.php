<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Services\ShipmentPaymentSummary;

class ShipmentPaymentController extends PortalController
{
    public function summary(Request $request, $shipment, ShipmentPaymentSummary $summary)
    {
        return $this->success($request, $summary->forShipment($this->ownedShipment($shipment)));
    }

    public function receipt(Request $request, $shipment, $receipt, ShipmentPaymentSummary $summary)
    {
        $model = $this->ownedShipment($shipment);
        $record = collect($summary->forShipment($model)['receipts'])->firstWhere('id', $receipt);
        abort_unless($record, 404);
        $amount = $record['amount']['currency'] . ' ' . number_format($record['amount']['amountMinor'] / 100, 2);
        $content = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment receipt</title>'
            . '<style>body{font:16px Arial,sans-serif;color:#012642;margin:32px auto;padding:24px;max-width:680px}h1{font-size:24px}dt{margin-top:20px;color:#52616b}dd{margin:6px 0;overflow-wrap:anywhere}strong{font-size:24px}</style></head><body><h1>New World Cargo</h1><h2>Payment receipt</h2><dl>'
            . '<dt>Receipt</dt><dd>' . e($record['number']) . '</dd><dt>Shipment</dt><dd>' . e($model->code) . '</dd>'
            . '<dt>Payment date</dt><dd>' . e($record['dateLabel']) . '</dd><dt>Payment method</dt><dd>' . e($record['method']) . '</dd>'
            . '<dt>Amount received</dt><dd><strong>' . e($amount) . '</strong></dd><dt>Status</dt><dd>' . ($record['refunded'] ? 'Refunded' : 'Payment recorded') . '</dd></dl></body></html>';
        return $this->success($request, ['filename' => 'new-world-cargo-receipt-' . $record['id'] . '.html', 'mimeType' => 'text/html;charset=utf-8', 'content' => $content]);
    }

    private function ownedShipment($id): Shipment
    {
        return Shipment::where('client_id', $this->customerContext->requireClient()->id)->findOrFail($id);
    }
}

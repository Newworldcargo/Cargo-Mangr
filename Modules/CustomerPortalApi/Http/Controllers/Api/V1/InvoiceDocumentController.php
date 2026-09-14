<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use App\Models\Transxn;
use Illuminate\Http\Request;

class InvoiceDocumentController extends PortalController
{
    public function invoice(Request $request, $invoice)
    {
        $model = $this->ownedInvoice($invoice);
        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Invoice not found.', 404);
        }

        $shipment = $model->shipment;
        $invoiceNumber = $model->receipt_number ?: ('INV-' . $model->id);
        $content = $this->html('Invoice ' . $invoiceNumber, '
            <div class="badge">' . e(strtoupper((string) $model->status)) . '</div>
            <h1>Customer invoice</h1>
            <p class="muted">' . e($invoiceNumber) . ' · Shipment ' . e(optional($shipment)->code ?: 'N/A') . '</p>
            <div class="total"><span>Total amount</span>' . e($this->money((float) $model->total, $model->currency ?: 'USD')) . '</div>
            <table>
                <tr><td>Customer</td><td>' . e(optional($shipment)->reciver_name ?: optional($shipment)->client_name ?: 'Customer') . '</td></tr>
                <tr><td>Route</td><td>' . e(trim((string) optional($shipment)->client_address)) . ' → ' . e(trim((string) optional($shipment)->reciver_address)) . '</td></tr>
                <tr><td>Payment status</td><td>' . e(ucwords(str_replace('_', ' ', (string) $model->status))) . '</td></tr>
            </table>
        ');

        return $this->success($request, [
            'filename' => 'new-worldcargo-invoice-' . strtolower(preg_replace('/[^A-Za-z0-9\-]+/', '-', $invoiceNumber)) . '.html',
            'mimeType' => 'text/html;charset=utf-8',
            'content' => $content,
        ]);
    }

    public function receipt(Request $request, $invoice)
    {
        $model = $this->ownedInvoice($invoice);

        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Invoice not found.', 404);
        }

        if (!$model->isCompleted()) {
            return $this->problem($request, 'RECEIPT_NOT_READY', 'This receipt is available after payment is confirmed.', 409);
        }

        $shipment = $model->shipment;
        $receiptNumber = $model->receipt_number ?: ('INV-' . $model->id);
        $amount = $this->money((float) $model->total, $model->currency ?: 'USD');
        $paidAt = $model->updated_at ? $model->updated_at->format('M j, Y g:i A') : 'Payment confirmed';
        $method = optional($model->nwcReceipt)->method_of_payment ?: 'Recorded payment';

        $content = $this->html('Payment receipt', '
            <div class="badge">PAYMENT RECEIVED</div>
            <h1>Payment receipt</h1>
            <p class="muted">' . e($receiptNumber) . ' · Shipment ' . e(optional($shipment)->code ?: 'N/A') . '<br>Paid ' . e($paidAt) . ' via ' . e($method) . '</p>
            <div class="total"><span>Amount paid</span>' . e($amount) . '</div>
            <table>
                <tr><td>Customer</td><td>' . e(optional($shipment)->reciver_name ?: optional($shipment)->client_name ?: 'Customer') . '</td></tr>
                <tr><td>Route</td><td>' . e(trim((string) optional($shipment)->client_address)) . ' → ' . e(trim((string) optional($shipment)->reciver_address)) . '</td></tr>
                <tr><td>Status</td><td>' . e(ucwords(str_replace('_', ' ', (string) $model->status))) . '</td></tr>
            </table>
        ');

        return $this->success($request, [
            'filename' => 'new-worldcargo-receipt-' . strtolower(preg_replace('/[^A-Za-z0-9\-]+/', '-', $receiptNumber)) . '.html',
            'mimeType' => 'text/html;charset=utf-8',
            'content' => $content,
        ]);
    }

    private function ownedInvoice($invoice)
    {
        $client = $this->customerContext->requireClient();
        return Transxn::query()
            ->whereKey($invoice)
            ->whereHas('shipment', function ($shipmentQuery) use ($client) {
                $shipmentQuery->where('client_id', $client->id);
            })
            ->with(['shipment', 'nwcReceipt'])
            ->first();
    }

    private function money(float $amount, string $currency): string
    {
        return strtoupper($currency) . ' ' . number_format($amount, 2);
    }

    private function html(string $title, string $body): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><style>body{margin:0;background:#f5f7f7;color:#012642;font-family:Arial,sans-serif}.page{max-width:680px;margin:32px auto;background:#fff;padding:38px;border-radius:20px;box-sizing:border-box}.brand{font-size:22px;font-weight:800;color:#012642}.badge{display:inline-block;margin-top:12px;padding:7px 11px;border-radius:999px;background:#dff3e8;color:#147145;font-size:12px;font-weight:700}.muted{color:#56707d;line-height:1.5}.total{margin:28px 0;padding:20px;border-radius:16px;background:#edf4f7;font-size:28px;font-weight:800}.total span{display:block;color:#56707d;font-size:12px;margin-bottom:5px}table{width:100%;border-collapse:collapse;margin-top:20px}td{padding:15px 0;border-top:1px solid #e3e9eb;vertical-align:top}td:last-child{text-align:right;font-weight:700}.foot{margin-top:32px;padding-top:18px;border-top:1px solid #e3e9eb;color:#56707d;font-size:12px;line-height:1.5}</style></head><body><main class="page"><div class="brand">NEW WORLDCARGO</div>' . $body . '<div class="foot">Generated by New WorldCargo from the customer portal record.</div></main></body></html>';
    }
}

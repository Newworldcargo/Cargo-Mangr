<?php

namespace Modules\CustomerPortalApi\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray($request)
    {
        $shipment = $this->shipment;
        $paid = in_array($this->status, ['completed', 'refund_requested', 'partially_refunded'], true);
        $total = (float) $this->total;
        $issuedAt = $this->created_at;

        return [
            'id' => (string) $this->id,
            'customerId' => (string) optional($shipment)->client_id,
            'invoiceNumber' => (string) $this->receipt_number,
            'shipmentId' => optional($shipment)->id ? (string) $shipment->id : null,
            'shipmentLabel' => optional($shipment)->code,
            'route' => [
                'origin' => optional($shipment)->getRawOriginal('client_address'),
                'destination' => optional($shipment)->getRawOriginal('reciver_address'),
            ],
            'issuedAt' => $issuedAt ? $issuedAt->toIso8601String() : null,
            'issuedAtLabel' => $issuedAt ? $issuedAt->format('M j, Y') : null,
            'dueAt' => null,
            'dueAtLabel' => null,
            'status' => $paid ? 'paid' : 'unpaid',
            'total' => [
                'currency' => 'USD',
                'amountMinor' => max(0, (int) round($total * 100)),
            ],
            'lineItems' => [[
                'id' => (string) $this->id,
                'description' => 'Shipment ' . (optional($shipment)->code ?: $this->receipt_number),
                'quantity' => 1,
                'unitPrice' => [
                    'currency' => 'USD',
                    'amountMinor' => max(0, (int) round($total * 100)),
                ],
                'total' => [
                    'currency' => 'USD',
                    'amountMinor' => max(0, (int) round($total * 100)),
                ],
            ]],
            'paymentMethod' => optional($this->nwcReceipt)->method_of_payment,
            'paidAt' => $paid && $this->updated_at ? $this->updated_at->toIso8601String() : null,
            'paidAtLabel' => $paid && $this->updated_at ? $this->updated_at->format('M j, Y') : null,
            'revision' => (int) ($this->revision ?: 1),
        ];
    }
}

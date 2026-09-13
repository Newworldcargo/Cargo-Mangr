<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use App\Models\NwcReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Cargo\Entities\Support as SupportCase;
use Modules\CustomerPortalApi\Models\PortalInvoicePreference;

class InvoiceActionController extends PortalController
{
    public function reminder(Request $request, $invoice)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->problem($request, 'VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $validator->errors()->toArray());
        }

        $receipt = $this->ownedInvoice($invoice);
        if (!$receipt) {
            return $this->problem($request, 'NOT_FOUND', 'Invoice not found.', 404);
        }

        $preference = $this->invoicePreference($receipt);
        $preference->reminder_enabled = $request->boolean('enabled');
        $preference->revision = ((int) ($preference->revision ?: 1)) + 1;
        $preference->save();

        return $this->success($request, [
            'invoiceId' => (string) $receipt->id,
            'reminderEnabled' => (bool) $preference->reminder_enabled,
            'revision' => (int) $preference->revision,
        ]);
    }

    public function dispute(Request $request, $invoice)
    {
        $receipt = $this->ownedInvoice($invoice);
        if (!$receipt) {
            return $this->problem($request, 'NOT_FOUND', 'Invoice not found.', 404);
        }

        $preference = $this->invoicePreference($receipt);
        $preference->disputed_at = $preference->disputed_at ?: now();
        $preference->dispute_status = 'open';
        $preference->revision = ((int) ($preference->revision ?: 1)) + 1;
        $preference->save();

        $case = new SupportCase();
        $case->user_id = $this->customerContext->user()->id;
        $case->category = 'billing';
        $case->subject = 'Invoice dispute: ' . ($receipt->receipt_number ?: $receipt->id);
        $case->priority = 'normal';
        $case->shipment_number = optional($receipt->shipment)->code;
        $case->message = 'Customer opened a billing dispute from the mobile app.';
        $case->status = 'open';
        $case->save();

        return $this->success($request, [
            'kind' => 'dispute',
            'title' => 'Charge review opened',
            'detail' => 'Our billing team will review this invoice and contact you with the next update.',
            'events' => [[
                'label' => 'Dispute received',
                'detail' => 'Support case #' . $case->id . ' was opened for invoice ' . ($receipt->receipt_number ?: $receipt->id) . '.',
                'time' => now()->toIso8601String(),
                'complete' => true,
            ], [
                'label' => 'Billing review',
                'detail' => 'The billing team will check the payment and shipment details.',
                'time' => 'Pending',
                'complete' => false,
            ]],
        ], 201);
    }

    private function invoicePreference($receipt)
    {
        $client = $this->customerContext->requireClient();

        return PortalInvoicePreference::firstOrCreate(
            ['client_id' => $client->id, 'invoice_id' => $receipt->id],
            ['revision' => 1]
        );
    }

    private function ownedInvoice($invoice)
    {
        $client = $this->customerContext->requireClient();

        return NwcReceipt::with('shipment')
            ->whereHas('shipment', function ($query) use ($client) {
                $query->where('client_id', $client->id);
            })
            ->whereKey($invoice)
            ->first();
    }
}

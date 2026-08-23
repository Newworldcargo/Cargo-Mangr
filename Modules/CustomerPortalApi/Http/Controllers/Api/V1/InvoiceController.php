<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use App\Models\Transxn;
use Modules\CustomerPortalApi\Http\Resources\InvoiceResource;

class InvoiceController extends PortalController
{
    public function index(Request $request)
    {
        $client = $this->customerContext->requireClient();
        $query = Transxn::query()
            ->whereHas('shipment', function ($shipmentQuery) use ($client) {
                $shipmentQuery->where('client_id', $client->id);
            })
            ->with(['shipment', 'nwcReceipt'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $needle = substr($search, 0, 100);
            $query->where(function ($searchQuery) use ($needle) {
                $searchQuery->where('receipt_number', 'like', '%' . $needle . '%')
                    ->orWhereHas('shipment', function ($shipmentQuery) use ($needle) {
                        $shipmentQuery->where('code', 'like', '%' . $needle . '%');
                    });
            });
        }

        $status = strtolower((string) $request->query('status', ''));
        if ($status === 'paid') {
            $query->whereIn('status', ['completed', 'refund_requested', 'partially_refunded']);
        } elseif ($status === 'unpaid') {
            $query->whereNotIn('status', ['completed', 'refund_requested', 'partially_refunded']);
        }

        $invoices = $query->limit(100)->get();

        return $this->success($request, $invoices->map(function ($invoice) use ($request) {
            return (new InvoiceResource($invoice))->resolve($request);
        })->values()->all());
    }

    public function show(Request $request, $invoice)
    {
        $client = $this->customerContext->requireClient();
        $model = Transxn::query()
            ->whereKey($invoice)
            ->whereHas('shipment', function ($shipmentQuery) use ($client) {
                $shipmentQuery->where('client_id', $client->id);
            })
            ->with(['shipment', 'nwcReceipt'])
            ->first();

        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Invoice not found.', 404);
        }

        return $this->success($request, (new InvoiceResource($model))->resolve($request));
    }
}

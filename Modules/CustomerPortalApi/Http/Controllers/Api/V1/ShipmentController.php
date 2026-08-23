<?php

namespace Modules\CustomerPortalApi\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Modules\Cargo\Entities\Shipment;
use Modules\CustomerPortalApi\Http\Resources\PublicTrackingResource;
use Modules\CustomerPortalApi\Http\Resources\ShipmentResource;

class ShipmentController extends PortalController
{
    public function index(Request $request)
    {
        $client = $this->customerContext->client();
        if (!$client) {
            return $this->problem($request, 'FORBIDDEN', 'This account is not enabled for the customer portal.', 403);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), config('customerportalapi.max_per_page', 50));
        $query = Shipment::query()
            ->where('client_id', $client->id)
            ->with(['consignment.trackingHistory', 'from_address', 'packages'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $search = trim((string) $request->query('q', ''));
            if ($search !== '') {
                $needle = substr($search, 0, 100);
                $query->where(function ($searchQuery) use ($needle) {
                    $searchQuery->where('code', 'like', '%' . $needle . '%')
                        ->orWhere('reciver_address', 'like', '%' . $needle . '%')
                        ->orWhere('to_address', 'like', '%' . $needle . '%')
                        ->orWhere('next_destination', 'like', '%' . $needle . '%')
                        ->orWhereHas('consignment', function ($consignmentQuery) use ($needle) {
                            $consignmentQuery->where('source', 'like', '%' . $needle . '%')
                                ->orWhere('destination', 'like', '%' . $needle . '%');
                        });
                });
            }

        $status = strtolower(trim((string) $request->query('status', '')));
        if ($status === 'delivered') {
            $query->where('status_id', Shipment::DELIVERED_STATUS);
        } elseif ($status === 'active') {
            $query->where(function ($statusQuery) {
                $statusQuery->whereNull('status_id')
                    ->orWhere('status_id', '!=', Shipment::DELIVERED_STATUS);
            });
        }

        $paginator = $query->cursorPaginate($perPage);
        $data = collect($paginator->items())->map(function ($shipment) use ($request) {
            return (new ShipmentResource($shipment))->resolve($request);
        })->values()->all();

        return $this->success($request, $data, 200, [
            'nextCursor' => optional($paginator->nextCursor())->encode(),
        ]);
    }

    public function show(Request $request, $shipment)
    {
        $client = $this->customerContext->client();
        if (!$client) {
            return $this->problem($request, 'FORBIDDEN', 'This account is not enabled for the customer portal.', 403);
        }

        $model = Shipment::query()
            ->where('client_id', $client->id)
            ->where('id', $shipment)
            ->with(['consignment.trackingHistory', 'from_address', 'packages'])
            ->first();

        if (!$model) {
            return $this->problem($request, 'NOT_FOUND', 'Shipment not found.', 404);
        }

        return $this->success($request, (new ShipmentResource($model))->resolve($request));
    }

    public function publicTracking(Request $request, $trackingNumber)
    {
        $shipment = Shipment::query()
            ->where('code', $trackingNumber)
            ->with(['consignment.trackingHistory', 'from_address', 'packages'])
            ->first();

        if (!$shipment) {
            return $this->problem($request, 'TRACKING_NOT_FOUND', 'Tracking number not found.', 404);
        }

        return $this->success($request, (new PublicTrackingResource($shipment))->resolve($request));
    }
}

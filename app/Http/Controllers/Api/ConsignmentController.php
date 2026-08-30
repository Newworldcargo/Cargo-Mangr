<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Cargo\Entities\Shipment;

class ConsignmentController extends Controller
{
    private function authorizeConsignmentMutation(): void
    {
        abort_unless(auth()->check() && (int) auth()->user()->role === User::ADMIN, 403);
    }

    /**
     * Get latest consignments paginated and filtered by branch_id
     * GET /api/consignments/latest?per_page=10&page=1&branch_id=1
     */
    public function getLatestConsignments(Request $request)
    {

        try {
            $perPage = (int) $request->query('per_page', 10);
            $page = (int) $request->query('page', 1);
            $branchId = $request->query('branch_id');
            $query = Consignment::orderBy('created_at', 'desc')->with('shipments.client');
            // if ($branchId) {
            //     $query = $query->where('branch_id', $branchId);
            // }
            // dd($query->first());
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $data = $paginator->items();
            $result = collect($data)->map(function ($consignment) {
                return [
                    'id' => $consignment->id,
                    'code' => $consignment->consignment_code,
                    'name' => $consignment->name,
                    'status' => $consignment->status,
                    'current_status' => $consignment->current_status,
                    'current_stage_name' => $consignment->getCurrentStageName(),
                    'cargo_type' => $consignment->cargo_type,
                    'created_at' => $consignment->created_at,
                    'updated_at' => $consignment->updated_at,
                    // 'shipments' => $consignment?->shipments,
                ];
            });
            return response()->json([
                'data' => $result,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function searchConsignments(Request $request)
    {
        $query = $request->query('query');
        $results = Consignment::where('consignment_code', 'LIKE', "%{$query}%")
                ->orWhere('name', 'LIKE', "%{$query}%")
                ->limit(10)
                ->get(['id', 'consignment_code as code', 'name']);

        return response()->json($results);
    }



    /**
     * Add shipments to a consignment
     */
    public function addShipmentsToConsignment(Request $request)
    {
        $this->authorizeConsignmentMutation();
        $shipmentIds = $request->input('shipment_ids');
        $consignmentId = $request->input('consignment_id');
        $consignment = Consignment::findOrFail($consignmentId);

        try {
            // Update the consignment_id for all selected shipments
            foreach ($shipmentIds as $shipmentId) {
                $shipment = Shipment::find($shipmentId);
                if ($shipment) {
                    $shipment->consignment_id = $consignmentId; // Ensure this is sent from the form
                    $shipment->save();
                }
            }
            return response()->json([
                'success' => true,
                'message' => count($shipmentIds) . ' shipments added to the consignment successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding shipments: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Remove a shipment from a consignment
     */
    public function removeShipmentFromConsignment($consignmentId, $shipmentId)
    {
        $this->authorizeConsignmentMutation();
        try {
            // Find the shipment and remove it from the consignment
            $shipment = Shipment::where('id', $shipmentId)
                ->where('consignment_id', $consignmentId)
                ->firstOrFail();

            // Remove association with consignment
            $shipment->consignment_id = null;
            $shipment->save();

            return response()->json([
                'success' => true,
                'message' => 'Shipment removed from consignment successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error removing shipment: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get consignment info and all parcels/shipments under it
     * GET /api/consignments/{consignment_id}
     */
    public function getConsignmentWithParcels($id)
    {
        try {
            $consignment = Consignment::with(['shipments.client', 'shipments' => function($q) {
                $q->with('consignment');
            }])->find($id);
            if (!$consignment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Consignment not found.'
                ], 404);
            }
            $shipments = $consignment->shipments->map(function ($shipment) {
                return [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->code,
                    'customer_id' => $shipment->client_id,
                    'weight' => $shipment->total_weight,
                    'cost' => $shipment->amount_to_be_collected ?? $shipment->shipping_cost,
                    'status' => $shipment->status_id,
                    'paid' => $shipment->paid,
                    'total_weight' => $shipment->total_weight ?? null,
                    'volume' => $shipment->volume ?? null,
                    'customer' => $shipment->client ? [
                        'id' => $shipment->client->id,
                        'name' => $shipment->client->name ?? null,
                        'email' => $shipment->client->email ?? null,
                    ] : null,
                    'consignment_id' => $shipment->consignment_id,
                    'created_at' => $shipment->created_at,
                    'updated_at' => $shipment->updated_at,
                ];
            });
            return response()->json([
                'consignment' => [
                    'id' => $consignment->id,
                    'code' => $consignment->consignment_code,
                    'name' => $consignment->name,
                    'status' => $consignment->status,
                    'current_status' => $consignment->current_status,
                    'current_stage_name' => $consignment->getCurrentStageName(),
                    'cargo_type' => $consignment->cargo_type,
                    'created_at' => $consignment->created_at,
                    'updated_at' => $consignment->updated_at,
                ],
                'parcels' => $shipments,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Get shipments for a consignment including package details.
     * GET /api/consignments/{consignmentId}/shipments
     */
    public function getShipmentsWithPackages($consignmentId)
    {
        try {
            $consignment = Consignment::with(['shipments.packageShipments.package'])->find($consignmentId);

            if (!$consignment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Consignment not found.',
                ], 404);
            }

            $shipments = $consignment->shipments->map(function (Shipment $shipment) {
                return [
                    'id' => $shipment->id,
                    'tracking_number' => $shipment->code,
                    'status' => $shipment->status_id,
                    'client_id' => $shipment->client_id,
                    'paid' => (bool) $shipment->paid,
                    'total_weight' => $shipment->total_weight,
                    'volume' => $shipment->volume,
                    'packages' => $shipment->packageShipments->map(function ($packageShipment) {
                        return [
                            'id' => $packageShipment->id,
                            'package_id' => $packageShipment->package_id,
                            'package_name' => optional($packageShipment->package)->name ?? null,
                            'description' => $packageShipment->description,
                            'weight' => $packageShipment->weight,
                            'length' => $packageShipment->length,
                            'width' => $packageShipment->width,
                            'height' => $packageShipment->height,
                            'quantity' => $packageShipment->qty,
                            'created_at' => $packageShipment->created_at,
                            'updated_at' => $packageShipment->updated_at,
                        ];
                    })->values(),
                    'created_at' => $shipment->created_at,
                    'updated_at' => $shipment->updated_at,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'consignment' => [
                    'id' => $consignment->id,
                    'code' => $consignment->consignment_code,
                    'name' => $consignment->name,
                    'status' => $consignment->status,
                    'current_status' => $consignment->current_status,
                    'current_stage_name' => $consignment->getCurrentStageName(),
                    'cargo_type' => $consignment->cargo_type,
                    'created_at' => $consignment->created_at,
                    'updated_at' => $consignment->updated_at,
                ],
                'shipments' => $shipments,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all consignments paginated
     * GET /api/consignments/all?per_page=10&page=1
     */
    public function getAllConsignments(Request $request)
    {
        try {
            $perPage = (int) $request->query('per_page', 10);
            $page = (int) $request->query('page', 1);
            $query = Consignment::orderBy('created_at', 'desc')->with('shipments.client');
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);
            $data = $paginator->items();
            $result = collect($data)->map(function ($consignment) {
                return [
                    'id' => $consignment->id,
                    'code' => $consignment->consignment_code,
                    'name' => $consignment->name,
                    'status' => $consignment->status,
                    'current_status' => $consignment->current_status,
                    'current_stage_name' => $consignment->getCurrentStageName(),
                    'cargo_type' => $consignment->cargo_type,
                    'created_at' => $consignment->created_at,
                    'updated_at' => $consignment->updated_at,
                    // 'shipments' => $consignment?->shipments,
                ];
            });
            return response()->json([
                'data' => $result,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

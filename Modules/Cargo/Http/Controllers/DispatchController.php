<?php

namespace Modules\Cargo\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cargo\Entities\Driver;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Entities\ShipmentDispatchEntry;
use Modules\Cargo\Services\BranchAccessService;
use Modules\Cargo\Services\ShipmentDispatchService;

class DispatchController extends Controller
{
    public function index(Request $request, ShipmentDispatchService $service, BranchAccessService $branches)
    {
        abort_unless($service->canView($request->user()), 403);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'assignment' => ['nullable', 'in:all,assigned,unassigned']]);
        $entries = ShipmentDispatchEntry::with(['shipment.branch', 'shipment.captain'])
            ->whereHas('shipment', function ($query) use ($request, $branches, $data) {
                if (!$branches->isTopAdmin($request->user())) {
                    $query->whereIn('branch_id', $branches->branchIdsFor($request->user()));
                }
                if ($search = trim($data['search'] ?? '')) {
                    $query->where(function ($q) use ($search) {
                        $q->where('code', 'like', '%' . $search . '%')->orWhere('reciver_name', 'like', '%' . $search . '%');
                    });
                }
                if (($data['assignment'] ?? '') === 'assigned') $query->whereNotNull('captain_id')->where('captain_id', '>', 0);
                if (($data['assignment'] ?? '') === 'unassigned') $query->where(fn ($q) => $q->whereNull('captain_id')->orWhere('captain_id', 0));
            })->orderBy('created_at')->orderBy('id')->paginate(25)->withQueryString();
        breadcrumb([['name' => __('cargo::view.dashboard'), 'path' => fr_route('admin.dashboard')], ['name' => 'Dispatch']]);
        return view('cargo::adminLte.pages.dispatch.index', compact('entries', 'service'));
    }

    public function drivers(Request $request, Shipment $shipment, ShipmentDispatchService $service)
    {
        abort_unless($service->canManage($request->user(), $shipment), 403);
        return response()->json(['drivers' => Driver::where('branch_id', $shipment->branch_id)
            ->where('is_archived', 0)->orderBy('name')->get(['id', 'name']),
            'currentDriver' => $shipment->captain?->name, 'missionAssigned' => (bool) $shipment->mission_id]);
    }

    public function store(Request $request, Shipment $shipment, ShipmentDispatchService $service)
    {
        abort_unless($service->canManage($request->user(), $shipment), 403);
        $data = $request->validate(['driver_id' => ['nullable', 'integer', 'min:1']]);
        $service->send($request->user(), $shipment->id, isset($data['driver_id']) ? (int) $data['driver_id'] : null);
        return response()->json(['message' => 'Shipment saved to dispatch.', 'url' => fr_route('shipments.dispatch.index')]);
    }
}

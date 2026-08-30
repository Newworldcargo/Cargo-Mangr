<?php

namespace Modules\Cargo\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Shipment;
use Modules\Cargo\Services\BranchAccessService;

class BranchMonitoringController extends Controller
{
    public function __construct(
        private readonly BranchAccessService $branchAccess,
        private readonly AuditLogService $auditLogs,
    )
    {
        $this->middleware('user_role:1');
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'consignment_id' => 'nullable|integer|exists:consignments,id',
            'customer_id' => 'nullable|integer|exists:clients,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'include_unassigned' => 'nullable|boolean',
        ]);

        $shipments = Shipment::query()->with(['branch', 'client', 'consignment']);
        foreach (['branch_id', 'consignment_id'] as $field) {
            if (!empty($filters[$field])) $shipments->where($field, $filters[$field]);
        }
        if (!empty($filters['customer_id'])) $shipments->where('client_id', $filters['customer_id']);
        if (!empty($filters['from'])) $shipments->whereDate('created_at', '>=', $filters['from']);
        if (!empty($filters['to'])) $shipments->whereDate('created_at', '<=', $filters['to']);

        $audits = AuditLog::query()->with('user');
        $auditScopeAvailable = $this->auditLogs->supportsBranchScope();
        if (!$auditScopeAvailable) {
            $audits->whereRaw('1 = 0');
        } elseif (!empty($filters['branch_id'])) {
            $audits->where('branch_id', $filters['branch_id']);
        } elseif (empty($filters['include_unassigned'])) {
            $audits->whereNotNull('branch_id');
        }
        if (!empty($filters['user_id'])) $audits->where('user_id', $filters['user_id']);
        if (!empty($filters['from'])) $audits->whereDate('created_at', '>=', $filters['from']);
        if (!empty($filters['to'])) $audits->whereDate('created_at', '<=', $filters['to']);

        return view('cargo::adminLte.pages.reports.branch-monitoring', [
            'filters' => $filters,
            'branches' => Branch::where('is_archived', 0)->orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'summary' => ['shipments' => (clone $shipments)->count(), 'audit_events' => (clone $audits)->count()],
            'auditScopeAvailable' => $auditScopeAvailable,
            'shipments' => $shipments->latest()->limit(50)->get(),
            'audits' => $audits->latest()->limit(50)->get(),
        ]);
    }

    public function preview(User $user)
    {
        $preview = $this->branchAccess->preview($user);
        $this->auditLogs->createLog('access_previewed', $user, User::class, [], $preview, 'Top-admin branch access preview.');
        return response()->json(['data' => $preview]);
    }
}

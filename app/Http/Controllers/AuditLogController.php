<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\OperationalScopeFilterService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService, private readonly OperationalScopeFilterService $scopeFilters)
    {
        $this->middleware('auth');
        $this->auditLogService = $auditLogService;
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = $perPage > 0 ? $perPage : 20;

        $viewer = $request->user();
        $scopeOptions = $this->scopeFilters->options($viewer, $viewer->can('view-audit-logs'));
        $selectedScope = $this->scopeFilters->selected(
            $viewer,
            $scopeOptions,
            (int) $request->input('branch_id') ?: null,
            $request->input('scope') === 'self' ? $viewer->id : ((int) $request->input('user_id') ?: null),
        );
        $logs = $this->auditLogService->getAllLogs($perPage, $selectedScope['selectedBranchId'], $selectedScope['selectedUserId']);

        if ($request->wantsJson()) {
            return response()->json($logs);
        }

        return view('adminLte.pages.audit.index', compact('logs', 'scopeOptions', 'selectedScope'));
    }
}

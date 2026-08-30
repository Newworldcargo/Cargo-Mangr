<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->middleware('auth');
        $this->auditLogService = $auditLogService;
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = $perPage > 0 ? $perPage : 20;

        $logs = $this->auditLogService->getAllLogs($perPage);

        if ($request->wantsJson()) {
            return response()->json($logs);
        }

        return view('adminLte.pages.audit.index', compact('logs'));
    }
}

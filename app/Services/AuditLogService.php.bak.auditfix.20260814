<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Create an audit log entry.
     *
     * @param string $event
     * @param mixed $auditable  Model or ID
     * @param string|null $auditableType
     * @param array $oldValues
     * @param array $newValues
     * @param string|null $description
     * @return AuditLog
     */
    public function createLog(
        string $event,
        $auditable,
        ?string $auditableType = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'user_id'        => $user?->id,
            'event'          => $event,
            'auditable_type' => $auditableType ?? (is_object($auditable) ? get_class($auditable) : null),
            'auditable_id'   => is_object($auditable) ? $auditable->id : $auditable,
            'description'    => $description,
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::header('User-Agent'),
        ]);
    }

    /**
     * Get logs for a specific model.
     *
     * @param mixed $auditable
     * @param string|null $auditableType
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsFor($auditable, ?string $auditableType = null)
    {
        return AuditLog::with('user')
            ->where('auditable_type', $auditableType ?? get_class($auditable))
            ->where('auditable_id', is_object($auditable) ? $auditable->id : $auditable)
            ->latest()
            ->get();
    }

    /**
     * Get all logs for a user.
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsByUser(int $userId)
    {
        return AuditLog::where('user_id', $userId)
            ->latest()
            ->get();
    }

    /**
     * Get all logs (paginated).
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllLogs(int $perPage = 20)
    {
        return AuditLog::with('user')->latest()->paginate($perPage);
    }
}

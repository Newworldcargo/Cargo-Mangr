<?php

namespace Modules\Billing\Services;

use App\Models\User;
use Modules\Cargo\Services\BranchAccessService;

final class SalesAccess
{
    public function customers($user, int $branchId)
    {
        return \Modules\Cargo\Entities\Client::where('is_archived', 0)->where(function ($query) use ($user, $branchId) {
            $query->where('branch_id', $branchId)->orWhere(function ($unassigned) use ($user, $branchId) {
                $unassigned->whereNull('branch_id');
                if ((int) $user->role !== User::ADMIN) {
                    $unassigned->whereExists(function ($shipments) use ($branchId) {
                        $shipments->selectRaw('1')->from('shipments')->whereColumn('shipments.client_id', 'clients.id')->where('shipments.branch_id', $branchId);
                    });
                }
            });
        });
    }

    public function canAct($user, string $action, string $type): bool
    {
        return $this->canView($user, $type) && ((int) $user->role === User::ADMIN || $user->can($action . '-billing-' . $type));
    }

    public function canView($user, string $type): bool
    {
        return $user && in_array($type, ['quotations', 'invoices'], true)
            && !in_array((int) $user->role, [4, 5], true)
            && ((int) $user->role === User::ADMIN || $user->can('view-billing-' . $type));
    }

    public function scope($query, $user, string $type = 'invoices')
    {
        if ((int) $user->role !== User::ADMIN && !$user->can('view-all-branch-billing-' . $type)) {
            $query->whereIn('branch_id', app(BranchAccessService::class)->branchIdsFor($user));
        }
        return $query;
    }
}

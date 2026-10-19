<?php

namespace Modules\Billing\Services;

use App\Models\User;
use Modules\Cargo\Services\BranchAccessService;

final class SalesAccess
{
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

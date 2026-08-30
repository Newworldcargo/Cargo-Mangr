<?php

namespace App\Services;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\Transxn;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Driver;
use Modules\Cargo\Entities\Staff;
use Modules\Cargo\Services\BranchAccessService;

/**
 * Supplies and validates the global branch/user/self filters used by
 * operational pages. It never expands the page's normal visibility scope.
 */
class OperationalScopeFilterService
{
    public function __construct(private readonly BranchAccessService $branches)
    {
    }

    public function options(User $viewer, bool $mayManageBranch): array
    {
        $branchId = $this->branches->branchIdFor($viewer);
        $isTopAdmin = $this->branches->isTopAdmin($viewer);
        $canFilterBranch = $isTopAdmin || ($branchId && $mayManageBranch);

        $branchOptions = $isTopAdmin
            ? Branch::orderBy('name')->get(['id', 'name'])
            : ($canFilterBranch ? Branch::whereKey($branchId)->get(['id', 'name']) : collect());

        $users = $isTopAdmin
            ? $this->activeOperationalUsers($viewer->id)
            : User::whereIn('id', $this->branchUserIds($branchId, $viewer->id))->orderBy('name')->get(['id', 'name', 'email']);

        return [
            'can_filter_branch' => (bool) $canFilterBranch,
            'can_filter_user' => $isTopAdmin || (bool) $canFilterBranch,
            'is_top_admin' => $isTopAdmin,
            'branches' => $branchOptions,
            'users' => $users,
        ];
    }

    public function selected(User $viewer, array $options, ?int $branchId, ?int $userId): array
    {
        $selectedBranchId = null;
        if ($branchId && $options['can_filter_branch'] && $options['branches']->contains('id', $branchId)) {
            $selectedBranchId = $branchId;
        }

        $selectedUserId = null;
        if ($userId && ($userId === $viewer->id
            || (($options['is_top_admin'] ?? false) && User::whereKey($userId)->exists())
            || ($options['can_filter_user'] && $options['users']->contains('id', $userId)))) {
            $selectedUserId = $userId;
        }

        return compact('selectedBranchId', 'selectedUserId');
    }

    private function branchUserIds(?int $branchId, int $viewerId)
    {
        if (!$branchId) {
            return collect([$viewerId]);
        }

        return collect([$viewerId])
            ->merge(Branch::whereKey($branchId)->pluck('user_id'))
            ->merge(Staff::where('branch_id', $branchId)->pluck('user_id'))
            ->merge(Driver::where('branch_id', $branchId)->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();
    }

    private function activeOperationalUsers(int $viewerId)
    {
        return User::query()
            ->where(function ($query) use ($viewerId) {
                $query->whereKey($viewerId)
                    ->orWhereIn('id', Transxn::query()->whereNotNull('cashier_user_id')->select('cashier_user_id'))
                    ->orWhereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}

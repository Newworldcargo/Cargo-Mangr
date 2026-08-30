<?php

namespace Modules\Cargo\Services;

use App\Models\User;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Staff;

/**
 * The Cargo module's single source of truth for branch access.
 *
 * It is intentionally read-only: callers can inspect an effective scope but
 * cannot impersonate a user or change the active authentication session.
 */
class BranchAccessService
{
    public function isTopAdmin(?User $user): bool
    {
        return $user && (int) $user->role === User::ADMIN;
    }

    public function branchIdFor(?User $user): ?int
    {
        if (!$user || $this->isTopAdmin($user)) {
            return null;
        }

        if ((int) $user->role === 3) {
            return Branch::where('user_id', $user->id)->value('id');
        }

        if ((int) $user->role === 2) {
            return Staff::where('user_id', $user->id)->value('branch_id');
        }

        return null;
    }

    public function preview(User $user): array
    {
        $branchId = $this->branchIdFor($user);
        $branch = $branchId ? Branch::find($branchId) : null;

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'email' => $user->email,
            'role' => $user->userRole,
            'branch_id' => $branchId,
            'branch_name' => $branch?->name,
            'scope' => $this->isTopAdmin($user) ? 'all_branches' : ($branchId ? 'assigned_branch' : 'no_branch_access'),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
        ];
    }
}

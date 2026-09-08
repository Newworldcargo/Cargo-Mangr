<?php

namespace Modules\Cargo\Services;

use App\Models\User;
use Modules\Cargo\Entities\Branch;
use Modules\Cargo\Entities\Staff;
use Modules\Currency\Entities\Currency;

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

        // The branch-user relationship is authoritative. Some historical
        // branch accounts (including Zimbabwe) have role 0 instead of 3, so
        // role-only detection incorrectly removed both branch scope and the
        // branch's configured currency.
        $ownedBranchId = Branch::where('user_id', $user->id)->value('id');
        if ($ownedBranchId) {
            return (int) $ownedBranchId;
        }

        // Historical installs use both 0 and 2 for staff accounts. Treating
        // only one as staff silently removed the branch boundary for the
        // other in audit and operational checks.
        if (in_array((int) $user->role, [User::STAFF, 2], true)) {
            return Staff::where('user_id', $user->id)->value('branch_id');
        }

        return null;
    }

    /**
     * Resolve the currency that an authenticated user should operate in.
     * A staff assignment takes precedence over the shipment's owning branch.
     * Historical Zambian branches with no saved currency use ZMW.
     */
    public function currencyFor(?User $user, ?Branch $fallbackBranch = null): string
    {
        return $this->currencyContextFor($user, $fallbackBranch)['currency'];
    }

    public function currencyContextFor(?User $user, ?Branch $fallbackBranch = null): array
    {
        $branchId = $this->branchIdFor($user);
        if ($branchId) {
            $branch = Branch::find($branchId);

            return [
                'currency' => strtoupper($branch?->default_currency ?: $this->systemCurrency()),
                'source' => 'assigned_branch',
                'branch_id' => $branch?->id,
                'branch_name' => $branch?->name,
            ];
        }

        if ($fallbackBranch?->default_currency) {
            return [
                'currency' => strtoupper($fallbackBranch->default_currency),
                'source' => 'shipment_branch',
                'branch_id' => $fallbackBranch->id,
                'branch_name' => $fallbackBranch->name,
            ];
        }

        return [
            'currency' => $this->systemCurrency(),
            'source' => 'system_default',
            'branch_id' => null,
            'branch_name' => null,
        ];
    }

    public function systemCurrency(): string
    {
        $currency = Currency::query()
            ->where('default', 1)
            ->where('status', 1)
            ->value('code');

        return strtoupper($currency ?: 'USD');
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

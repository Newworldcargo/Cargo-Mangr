<?php

namespace Modules\Users\Services;

use App\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * Maintains a single, auditable admin-to-user impersonation context.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public function isActive(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public function impersonator(): ?User
    {
        $id = Session::get(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }

    public function canImpersonate(?User $actor, User $target): bool
    {
        if (!$actor || $this->isActive() || $actor->is($target)) {
            return false;
        }

        // Only a top admin may enter another top-admin account.
        if ((int) $target->role === User::ADMIN && (int) $actor->role !== User::ADMIN) {
            return false;
        }

        return (int) $actor->role === User::ADMIN || $actor->can('impersonate-users');
    }
}

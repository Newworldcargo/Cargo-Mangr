<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use App\Models\User;

class PortalEmailRequirement
{
    public function needsRecovery(User $user): bool
    {
        $email = strtolower(trim((string) $user->email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return true;
        $domain = substr($email, strrpos($email, '@') + 1);
        return $domain === 'invalid' || str_ends_with($domain, '.invalid');
    }
}

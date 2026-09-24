<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PortalAccountIdentifier
{
    public function find(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if (str_contains($identifier, '@')) {
            $users = User::whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])->limit(2)->get();
            return $users->count() === 1 ? $users->first() : null;
        }
        $phone = RegistrationPhone::normalise($identifier);
        if (!$phone) return null;
        $owners = [];
        foreach (['users', 'clients', 'staffs'] as $table) {
            $fields = $table === 'staffs' ? ['responsible_mobile'] : ['responsible_mobile', 'secondary_mobile'];
            foreach (DB::table($table)->select(array_merge(['id'], $table === 'users' ? [] : ['user_id'], $fields))->cursor() as $row) {
                foreach ($fields as $field) {
                    if (RegistrationPhone::normalise((string) $row->$field) === $phone) {
                        $owner = $table === 'users' ? 'user:'.$row->id : ($row->user_id ? 'user:'.$row->user_id : $table.':'.$row->id);
                        $owners[$owner] = true;
                    }
                }
            }
        }
        if (count($owners) !== 1 || !str_starts_with(array_key_first($owners), 'user:')) return null;
        return User::find(substr(array_key_first($owners), 5));
    }
}

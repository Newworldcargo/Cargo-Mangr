<?php

namespace Modules\CustomerPortalApi\Services\Portal;

use Illuminate\Support\Facades\DB;

class RegistrationPhone
{
    public static function normalise(string $value): ?string
    {
        if (preg_match('/[^0-9+().\s-]/', $value)) return null;
        $number = preg_replace('/\D/', '', $value);
        if (str_starts_with($number, '00')) $number = substr($number, 2);
        if (preg_match('/^0[679]\d{8}$/', $number)) $number = '260' . substr($number, 1);
        elseif (preg_match('/^[679]\d{8}$/', $number)) $number = '260' . $number;
        return preg_match('/^[1-9]\d{7,14}$/', $number) ? $number : null;
    }

    public function exists(string $number): bool
    {
        // Legacy fields use mixed formats; do not match shipment recipients or notes.
        foreach (['users', 'clients'] as $table) {
            foreach (DB::table($table)->select('responsible_mobile', 'secondary_mobile')
                ->where(function ($query) {
                    $query->whereNotNull('responsible_mobile')->orWhereNotNull('secondary_mobile');
                })->cursor() as $row) {
                foreach (['responsible_mobile', 'secondary_mobile'] as $field) {
                    if (self::normalise((string) $row->$field) === $number) return true;
                }
            }
        }
        return false;
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportStaffPhoneGuard
{
    private ?array $phones = null;

    public function assertAllowed(array $numbers, ?int $customerId = null): void
    {
        $this->load();
        $customer = $customerId ? DB::table('clients')->where('id', $customerId)->where('is_archived', 0)->first(['user_id']) : null;
        foreach ($numbers as $number) {
            $phone = ConsignmentCustomerMatcher::phone($number);
            if ($phone && isset($this->phones[$phone])) {
                $owners = array_keys($this->phones[$phone]);
                if ($customer && count($owners) === 1 && (int) $owners[0] === (int) $customer->user_id && $customer->user_id) continue;
                throw ValidationException::withMessages([
                    'customer' => 'This staff phone number can only be used for the staff member\'s own customer account. Enter the actual customer phone number or exclude this row.',
                ]);
            }
        }
    }

    private function add($row, int $userId): void
    {
        foreach (['responsible_mobile', 'secondary_mobile'] as $field) {
            if ($phone = ConsignmentCustomerMatcher::phone($row->$field ?? null)) $this->phones[$phone][$userId] = true;
        }
    }

    private function load(): void
    {
        if ($this->phones !== null) return;
        $this->phones = [];
        // Legacy staff use role 2; role 3 is a branch account. Staff-profile
        // membership also covers employees with a customer or migrated role.
        $staffUsers = DB::table('users')->whereIn('role', [User::STAFF, User::ADMIN, 2, 3])
            ->orWhereIn('id', DB::table('staffs')->select('user_id'));
        $ids = $staffUsers->pluck('id');
        foreach (DB::table('users')->whereIn('id', $ids)->get(['id', 'responsible_mobile', 'secondary_mobile']) as $row) $this->add($row, (int) $row->id);
        foreach (DB::table('clients')->whereIn('user_id', $ids)->get(['user_id', 'responsible_mobile', 'secondary_mobile']) as $row) $this->add($row, (int) $row->user_id);
        foreach (DB::table('staffs')->get(['user_id', 'responsible_mobile']) as $row) $this->add($row, (int) $row->user_id);
    }
}

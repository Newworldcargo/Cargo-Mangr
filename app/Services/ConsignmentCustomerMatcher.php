<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Client;

class ConsignmentCustomerMatcher
{
    private bool $loaded = false;
    private array $owners = [];
    private array $profiles = [];
    private array $names = [];
    private ?ImportStaffPhoneGuard $staffPhones = null;

    public static function phone(?string $value): ?string
    {
        if (!$value || preg_match('/[^0-9+().\s-]/', $value)) return null;
        $number = preg_replace('/\D/', '', $value);
        if (str_starts_with($number, '00')) $number = substr($number, 2);
        if (preg_match('/^(260|263)0(\d{9})$/', $number, $parts)) $number = $parts[1].$parts[2];
        if (preg_match('/^0[679]\d{8}$/', $number)) $number = '260'.substr($number, 1);
        elseif (preg_match('/^[679]\d{8}$/', $number)) $number = '260'.$number;
        return preg_match('/^[1-9]\d{7,14}$/', $number) ? $number : null;
    }

    private function name(string $value): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY);
        sort($words);
        return implode(' ', $words);
    }

    private function addPhones(string $owner, $row): void
    {
        foreach (['responsible_mobile', 'secondary_mobile'] as $field) {
            if ($phone = self::phone($row->$field)) $this->owners[$phone][$owner] = true;
        }
    }

    private function load(): void
    {
        if ($this->loaded) return;
        // Build once per import request, not once per spreadsheet row. Include
        // archived/unlinked owners so their numbers cannot silently be reassigned.
        foreach (DB::table('users')->select('id', 'name', 'responsible_mobile', 'secondary_mobile')->cursor() as $user) {
            $owner = 'user:'.$user->id;
            $this->names[$owner][] = $this->name($user->name);
            $this->addPhones($owner, $user);
        }
        foreach (DB::table('clients')->select('id', 'user_id', 'name', 'is_archived', 'responsible_mobile', 'secondary_mobile')->cursor() as $client) {
            $this->indexClient($client);
        }
        foreach (DB::table('staffs')->select('user_id', 'responsible_mobile')->cursor() as $staff) {
            if ($phone = self::phone($staff->responsible_mobile)) $this->owners[$phone]['user:'.$staff->user_id] = true;
        }
        $this->loaded = true;
    }

    private function indexClient($client): void
    {
        $owner = $client->user_id ? 'user:'.$client->user_id : 'client:'.$client->id;
        $this->addPhones($owner, $client);
        if (!$client->is_archived) {
            $this->profiles[$owner][$client->id] = true;
            $this->names[$owner][] = $this->name($client->name);
        }
    }

    public function remember(Client $client): void
    {
        $this->load();
        $this->indexClient($client);
    }

    public function find(string $phone, string $name, ?string $secondary = null): ?Client
    {
        $this->staffPhones ??= new ImportStaffPhoneGuard();
        $this->load();
        $owners = [];
        foreach (array_filter([$phone, $secondary]) as $value) {
            $normalized = self::phone($value);
            if (!$normalized) {
                throw ValidationException::withMessages(['customer' => 'Check the customer phone number and include its country code.']);
            }
            $owners += $this->owners[$normalized] ?? [];
        }
        if (!$owners) {
            $this->staffPhones->assertAllowed([$phone, $secondary]);
            return null;
        }
        if (count($owners) !== 1) {
            throw ValidationException::withMessages(['customer' => 'These phone details match more than one account. Confirm the customer and correct the contact details before importing this row.']);
        }
        $owner = array_key_first($owners);
        $profiles = $this->profiles[$owner] ?? [];
        if (count($profiles) !== 1) {
            throw ValidationException::withMessages(['customer' => 'This phone belongs to an account without a single active customer profile. Review the account before importing this row.']);
        }
        if (!$this->name($name) || !in_array($this->name($name), $this->names[$owner] ?? [], true)) {
            throw ValidationException::withMessages(['customer' => 'This phone is already linked to a different customer name. Confirm the name and number before importing this row.']);
        }
        $client = Client::findOrFail(array_key_first($profiles));
        $this->staffPhones->assertAllowed([$phone, $secondary], $client->id);
        return $client;
    }
}

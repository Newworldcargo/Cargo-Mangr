<?php

namespace App\Services;

use App\Models\ConsignmentImportBatch;
use App\Models\ConsignmentImportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Cargo\Entities\Client;

class ImportCustomerSelection
{
    public function fingerprint(ConsignmentImportRow $row, ConsignmentImportBatch $batch): string
    {
        return hash('sha256', json_encode([$row->raw_values, $batch->mappings, $batch->selected_sheet]));
    }

    public function customer(int $id): Client
    {
        $client = Client::whereKey($id)->where('is_archived', 0)->first();
        if (!$client || !$client->user_id || !User::whereKey($client->user_id)->exists()
            || Client::where('user_id', $client->user_id)->where('is_archived', 0)->count() !== 1) {
            throw ValidationException::withMessages(['customer' => 'Choose an active customer with one linked account. This profile needs account review.']);
        }
        return $client;
    }

    public function contacts(Client $client): array
    {
        $user = User::findOrFail($client->user_id);
        $phone = ConsignmentCustomerMatcher::phone($client->responsible_mobile)
            ?? ConsignmentCustomerMatcher::phone($user->responsible_mobile);
        $secondary = ConsignmentCustomerMatcher::phone($client->secondary_mobile)
            ?? ConsignmentCustomerMatcher::phone($user->secondary_mobile);
        if (!$phone) $phone = $secondary;
        if ($phone === $secondary) $secondary = null;
        if (!$phone) throw ValidationException::withMessages(['customer' => 'This customer has no valid saved phone number. Update their profile before selecting them.']);
        (new ImportStaffPhoneGuard())->assertAllowed([$phone, $secondary], $client->id);
        return ['consignee_name' => $client->name, 'phone' => $phone, 'phone_2' => $secondary, '_selected_customer_id' => $client->id];
    }

    public function apply(array $mapped, ConsignmentImportRow $row, ConsignmentImportBatch $batch): array
    {
        if (!hash_equals((string) $row->customer_source_hash, $this->fingerprint($row, $batch))) {
            throw ValidationException::withMessages(['customer' => 'The source details changed. Review and choose the customer again.']);
        }
        return array_merge($mapped, $this->contacts($this->customer((int) $row->selected_customer_id)));
    }

    public function create(array $data, ConsignmentImportBatch $batch): Client
    {
        $phone = ConsignmentCustomerMatcher::phone($data['phone']);
        $secondary = empty($data['phone_2']) ? null : ConsignmentCustomerMatcher::phone($data['phone_2']);
        if (!$phone || (!empty($data['phone_2']) && !$secondary) || $phone === $secondary) {
            throw ValidationException::withMessages(['phone' => 'Enter valid, different phone numbers including the country code.']);
        }
        // Reject existing owners even when their stored phone formatting differs.
        foreach (['users', 'clients', 'staffs'] as $table) {
            $fields = $table === 'staffs' ? ['responsible_mobile'] : ['responsible_mobile', 'secondary_mobile'];
            foreach (DB::table($table)->select($fields)->cursor() as $record) {
                foreach ($fields as $field) {
                    $existing = ConsignmentCustomerMatcher::phone($record->$field);
                    if ($existing && in_array($existing, array_filter([$phone, $secondary]), true)) {
                        throw ValidationException::withMessages(['phone' => 'This phone is already on an account. Search and select the existing customer instead.']);
                    }
                }
            }
        }
        $email = strtolower(trim($data['email'] ?? ''));
        if ($email && (User::whereRaw('LOWER(email) = ?', [$email])->exists() || Client::whereRaw('LOWER(email) = ?', [$email])->exists())) {
            throw ValidationException::withMessages(['email' => 'This email already exists. Search and select the existing customer instead.']);
        }
        $name = trim($data['first_name'].' '.$data['last_name']);
        $email = $email ?: 'imported+'.Str::uuid().'@newworldcargo.invalid';
        $user = User::create(['name' => $name, 'email' => $email, 'password' => bcrypt(Str::random(48)),
            'role' => 4, 'verified' => false, 'responsible_mobile' => $phone, 'secondary_mobile' => $secondary]);
        $client = Client::create(['code' => 0, 'user_id' => $user->id, 'name' => $name, 'email' => $email,
            'responsible_name' => $data['contact_name'] ?: $name, 'responsible_mobile' => $phone, 'secondary_mobile' => $secondary,
            'national_id' => $data['national_id'] ?? null, 'branch_id' => $batch->pickup_branch_id,
            'is_archived' => 0, 'created_by' => auth()->id()]);
        $client->update(['code' => $client->id]);
        if (!empty($data['address'])) {
            if (!$batch->from_country_id || !$batch->from_state_id) {
                throw ValidationException::withMessages(['address' => 'Save the pickup branch location in the import setup before adding an address.']);
            }
            \Modules\Cargo\Entities\ClientAddress::create(['client_id' => $client->id, 'address' => $data['address'],
                'country_id' => $batch->from_country_id, 'state_id' => $batch->from_state_id]);
        }
        return $client;
    }
}

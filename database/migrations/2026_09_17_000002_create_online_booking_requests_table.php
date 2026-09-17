<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('online_booking_requests')) {
            Schema::create('online_booking_requests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('reference', 32)->unique();
                $table->unsignedInteger('client_id')->index();
                $table->unsignedInteger('branch_id')->index();
                $table->string('service', 24)->index();
                $table->string('transport_mode', 12)->nullable()->index();
                $table->string('status', 24)->default('pending')->index();
                $table->string('pickup_address', 1000);
                $table->string('destination_address', 1000);
                $table->string('recipient_name', 255);
                $table->string('recipient_phone', 60);
                $table->string('sender_name', 255)->nullable();
                $table->string('sender_phone', 60)->nullable();
                $table->string('schedule', 255)->nullable();
                $table->text('instructions')->nullable();
                $table->decimal('total_weight', 12, 3)->default(0);
                $table->decimal('quoted_amount', 14, 2)->default(0);
                $table->string('currency', 3)->default('ZMW');
                $table->json('payload');
                $table->unsignedBigInteger('shipment_id')->nullable()->unique();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->timestamp('submitted_at')->nullable()->index();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('customer_portal_shipment_drafts')
            && !Schema::hasColumn('customer_portal_shipment_drafts', 'online_booking_id')) {
            Schema::table('customer_portal_shipment_drafts', function (Blueprint $table) {
                $table->unsignedBigInteger('online_booking_id')->nullable()->index()->after('shipment_id');
            });
        }

        $this->addPermission('manage-online-bookings');
        $this->backfillExistingPortalBookings();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function addPermission(string $name): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_groups')) {
            return;
        }

        $now = now();
        $groupId = DB::table('permission_groups')->where('name', 'shipments')->value('id');
        if (!$groupId) {
            $groupId = DB::table('permission_groups')->insertGetId([
                'name' => 'shipments', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => $name,
            'guard_name' => 'web',
            'permission_group_id' => $groupId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function backfillExistingPortalBookings(): void
    {
        if (!Schema::hasTable('customer_portal_shipment_drafts') || !Schema::hasTable('shipments')) {
            return;
        }

        $drafts = DB::table('customer_portal_shipment_drafts')
            ->whereNotNull('shipment_id')
            ->whereNull('online_booking_id')
            ->orderBy('id')
            ->get();

        foreach ($drafts as $draft) {
            $shipment = DB::table('shipments')->where('id', $draft->shipment_id)->first();
            if (!$shipment) {
                continue;
            }

            $payload = json_decode((string) $draft->payload, true) ?: [];
            $form = (array) ($payload['form'] ?? []);
            $service = in_array($payload['service'] ?? null, ['local', 'intercity', 'import', 'custom'], true)
                ? $payload['service'] : 'custom';
            $quote = $draft->quote_id
                ? DB::table('customer_portal_quotes')->where('id', $draft->quote_id)->first()
                : null;
            $weight = collect((array) ($payload['cargoRows'] ?? []))->sum(fn ($row) => (float) ($row['weight'] ?? 0));
            $now = $draft->created_at ?: now();

            $bookingId = DB::table('online_booking_requests')->insertGetId([
                'reference' => 'OBR-H' . $draft->id,
                'client_id' => $draft->client_id,
                'branch_id' => $shipment->branch_id,
                'service' => $service,
                'transport_mode' => in_array(strtolower((string) ($form['transportMode'] ?? ($quote->transport_mode ?? ''))), ['air', 'sea'], true)
                    ? strtolower((string) ($form['transportMode'] ?? $quote->transport_mode)) : null,
                'status' => 'accepted',
                'pickup_address' => $form['pickup'] ?? $shipment->client_address ?? 'Not provided',
                'destination_address' => $form['destination'] ?? $shipment->reciver_address ?? 'Not provided',
                'recipient_name' => $form['recipient'] ?? $shipment->reciver_name ?? 'Customer',
                'recipient_phone' => $form['phone'] ?? $shipment->reciver_phone ?? 'Not provided',
                'sender_name' => $form['sender'] ?? null,
                'sender_phone' => $form['senderPhone'] ?? null,
                'schedule' => $form['schedule'] ?? null,
                'instructions' => $form['instructions'] ?? null,
                'total_weight' => $weight ?: ($shipment->total_weight ?? 0),
                'quoted_amount' => $quote ? ((int) $quote->amount_minor / 100) : ($shipment->amount_to_be_collected ?? 0),
                'currency' => strtoupper((string) ($quote->currency ?? 'ZMW')),
                'payload' => json_encode($payload),
                'shipment_id' => $shipment->id,
                'submitted_at' => $now,
                'processed_at' => $shipment->created_at ?: $now,
                'created_at' => $now,
                'updated_at' => now(),
            ]);

            DB::table('customer_portal_shipment_drafts')->where('id', $draft->id)->update([
                'online_booking_id' => $bookingId,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_portal_shipment_drafts')
            && Schema::hasColumn('customer_portal_shipment_drafts', 'online_booking_id')) {
            Schema::table('customer_portal_shipment_drafts', function (Blueprint $table) {
                $table->dropColumn('online_booking_id');
            });
        }

        Schema::dropIfExists('online_booking_requests');

        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('name', 'manage-online-bookings')->value('id');
            if ($permissionId) {
                if (Schema::hasTable('model_has_permissions')) DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
                if (Schema::hasTable('role_has_permissions')) DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

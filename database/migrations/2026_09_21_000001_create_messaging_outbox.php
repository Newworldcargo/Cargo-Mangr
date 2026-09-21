<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('messaging_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('email_enabled')->default(false);
            $table->string('email')->nullable();
            $table->text('password')->nullable();
            $table->string('sender_txn', 11)->nullable();
            $table->string('sender_otp', 11)->nullable();
            $table->json('sms_purposes')->nullable();
            $table->unsignedInteger('sms_per_minute')->default(30);
            $table->unsignedInteger('email_per_minute')->default(30);
            $table->timestamps();
        });
        Schema::create('messaging_jobs', function (Blueprint $table) {
            $table->bigIncrements('id'); $table->string('queue')->index();
            $table->longText('payload'); $table->unsignedInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at'); $table->unsignedInteger('created_at');
        });
        Schema::create('messaging_campaigns', function (Blueprint $table) {
            $table->id(); $table->uuid('request_key')->unique();
            $table->unsignedBigInteger('created_by');
            $table->string('channel', 10); $table->string('audience', 20);
            $table->text('content'); $table->string('status')->default('pending');
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedBigInteger('last_user_id')->default(0);
            $table->unsignedInteger('queued')->default(0); $table->unsignedInteger('skipped')->default(0);
            $table->timestamps();
        });
        Schema::create('messaging_outbox', function (Blueprint $table) {
            $table->id(); $table->string('dedupe_key', 64)->unique();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('channel', 10); $table->string('purpose', 40);
            $table->text('recipient'); $table->longText('content');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_id')->nullable(); $table->string('last_error')->nullable();
            $table->timestamp('available_at'); $table->timestamp('expires_at');
            $table->timestamp('started_at')->nullable(); $table->timestamp('accepted_at')->nullable();
            $table->timestamps(); $table->index(['status', 'available_at']);
        });
        if (Schema::hasTable('permissions') && Schema::hasTable('permission_groups')) {
            $group = DB::table('permission_groups')->where('name', 'messaging')->value('id');
            if (!$group) $group = DB::table('permission_groups')->insertGetId(['name' => 'messaging', 'created_at' => now(), 'updated_at' => now()]);
            foreach (['manage-messaging-settings', 'send-bulk-messages', 'view-messaging-history'] as $permission) {
                DB::table('permissions')->insertOrIgnore(['name' => $permission, 'guard_name' => 'web', 'permission_group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
    public function down(): void
    {
        // Message history and permissions are intentionally retained on rollback.
    }
};

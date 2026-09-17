<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'view-online-bookings';
    private const USER_MODEL = 'App\\Models\\User';

    public function up(): void
    {
        if (Schema::hasTable('shipments') && !Schema::hasColumn('shipments', 'booking_source')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->string('booking_source', 32)->nullable()->index()->after('order_id');
            });

            DB::table('shipments')
                ->where('order_id', 'like', 'PORTAL-%')
                ->update(['booking_source' => 'customer_portal']);
        }

        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_groups')) {
            return;
        }

        $now = now();
        $groupId = DB::table('permission_groups')->where('name', 'shipments')->value('id');
        if (!$groupId) {
            $groupId = DB::table('permission_groups')->insertGetId([
                'name' => 'shipments',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'guard_name' => 'web',
                'permission_group_id' => $groupId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('staffs') && Schema::hasTable('branches') && Schema::hasTable('model_has_permissions')) {
            $lusakaBranchIds = DB::table('branches')
                ->where('name', 'like', '%Lusaka%')
                ->pluck('id');

            $userIds = DB::table('staffs')
                ->whereIn('branch_id', $lusakaBranchIds)
                ->where('is_archived', 0)
                ->whereNotNull('user_id')
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'model_type' => self::USER_MODEL,
                    'model_id' => $userId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
            if ($permissionId) {
                DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        if (Schema::hasTable('shipments') && Schema::hasColumn('shipments', 'booking_source')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('booking_source');
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

class AddGlobalSearchPermission extends Migration
{
    private const PERMISSION = 'use-global-search';
    private const USER_MODEL = 'App\\Models\\User';

    public function up()
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_groups')) {
            return;
        }

        $now = now();
        $groupId = DB::table('permission_groups')->where('name', 'system')->value('id');
        if (!$groupId) {
            $groupId = DB::table('permission_groups')->insertGetId([
                'name' => 'system',
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

        $branchUserIds = Schema::hasTable('branches')
            ? DB::table('branches')->whereNotNull('user_id')->pluck('user_id')
            : collect();
        $staffUserIds = Schema::hasTable('staffs')
            ? DB::table('staffs')->whereNotNull('user_id')->pluck('user_id')
            : collect();
        $internalUserIds = $branchUserIds->merge($staffUserIds)->filter()->unique()->values();

        if ($internalUserIds->isNotEmpty() && Schema::hasTable('model_has_permissions')) {
            foreach ($internalUserIds as $userId) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'model_type' => self::USER_MODEL,
                    'model_id' => $userId,
                ]);
            }
        }

        // Grant it through the existing internal roles too, so future staff who
        // receive those roles inherit search access without code changes.
        if ($internalUserIds->isNotEmpty() && Schema::hasTable('model_has_roles') && Schema::hasTable('role_has_permissions')) {
            $roleIds = DB::table('model_has_roles')
                ->where('model_type', self::USER_MODEL)
                ->whereIn('model_id', $internalUserIds)
                ->pluck('role_id')
                ->unique();

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
        if (!$permissionId) {
            return;
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        }
        if (Schema::hasTable('model_has_permissions')) {
            DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
        }
        DB::table('permissions')->where('id', $permissionId)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

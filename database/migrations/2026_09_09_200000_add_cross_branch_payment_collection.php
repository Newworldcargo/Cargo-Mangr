<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'collect-cross-branch-payments';

    private const PAYMENT_TABLES = [
        'transxns',
        'nwc_receipts',
        'shipment_payment_receipts',
    ];

    public function up(): void
    {
        foreach (self::PAYMENT_TABLES as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'collection_branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                // branches.id is an unsigned INT in this legacy installation.
                $table->unsignedInteger('collection_branch_id')->nullable()->index();
                $table->foreign('collection_branch_id')
                    ->references('id')
                    ->on('branches')
                    ->onDelete('set null');
            });
        }

        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_groups')) {
            return;
        }

        $now = now();
        $groupId = DB::table('permission_groups')
            ->where('name', 'transactions and finance')
            ->value('id');

        if (!$groupId) {
            $groupId = DB::table('permission_groups')->insertGetId([
                'name' => 'transactions and finance',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => self::PERMISSION,
            'guard_name' => 'web',
            'permission_group_id' => $groupId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');
            if ($permissionId) {
                if (Schema::hasTable('role_has_permissions')) {
                    DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
                }
                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
                }
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        foreach (array_reverse(self::PAYMENT_TABLES) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'collection_branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['collection_branch_id']);
                $table->dropColumn('collection_branch_id');
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

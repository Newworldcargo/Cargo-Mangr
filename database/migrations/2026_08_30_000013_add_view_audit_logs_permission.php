<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddViewAuditLogsPermission extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_groups')) return;
        $groupId = DB::table('permission_groups')->where('name', 'audit')->value('id');
        if (!$groupId) $groupId = DB::table('permission_groups')->insertGetId(['name' => 'audit', 'created_at' => now(), 'updated_at' => now()]);
        if (!DB::table('permissions')->where('name', 'view-audit-logs')->exists()) {
            DB::table('permissions')->insert(['name' => 'view-audit-logs', 'guard_name' => 'web', 'permission_group_id' => $groupId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
    public function down() { if (Schema::hasTable('permissions')) DB::table('permissions')->where('name', 'view-audit-logs')->delete(); }
}

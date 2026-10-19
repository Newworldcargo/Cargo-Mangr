<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RegisterSalesPermissions extends Migration
{
    public function up()
    {
        $group = DB::table('permission_groups')->where('name', 'sales')->value('id');
        if (!$group) $group = DB::table('permission_groups')->insertGetId(['name' => 'sales', 'created_at' => now(), 'updated_at' => now()]);
        $names = (require __DIR__ . '/../../Config/config.php')['permissions']['sales'];
        foreach ($names as $name) DB::table('permissions')->insertOrIgnore([
            'name' => $name, 'guard_name' => 'web', 'permission_group_id' => $group, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        // Preserve configured role grants during application rollback.
    }
}

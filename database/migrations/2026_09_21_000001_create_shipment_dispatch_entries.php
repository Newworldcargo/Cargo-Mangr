<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_dispatch_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('shipment_id')->unique();
            $table->unsignedInteger('requested_by');
            $table->timestamps();
            $table->index('created_at');
        });

        $group = DB::table('permission_groups')->where('name', 'shipments')->value('id');
        if (!$group) return;
        $roles = DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->whereIn('permissions.name', ['manage-shipments', 'assigned-shipments'])->distinct()->pluck('role_id');
        foreach (['view-dispatch', 'manage-dispatch'] as $name) {
            $id = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id')
                ?: DB::table('permissions')->insertGetId(['name' => $name, 'guard_name' => 'web',
                    'permission_group_id' => $group, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($roles as $role) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $id]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_dispatch_entries');
    }
};

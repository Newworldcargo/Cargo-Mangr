<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $view = DB::table('permissions')->where('name', 'view-online-bookings')->value('id');
        $manage = DB::table('permissions')->where('name', 'manage-online-bookings')->value('id');
        $edit = DB::table('permissions')->where('name', 'edit-shipments')->value('id');
        if (!$view || !$manage || !$edit) return;

        $editRoles = DB::table('role_has_permissions')->where('permission_id', $edit)->pluck('role_id');
        $editUsers = DB::table('model_has_permissions')->where('permission_id', $edit)
            ->where('model_type', 'App\\Models\\User')->pluck('model_id');
        $roleUsers = DB::table('model_has_roles')->whereIn('role_id', $editRoles)
            ->where('model_type', 'App\\Models\\User')->pluck('model_id');
        $eligible = $editUsers->merge($roleUsers)->unique();

        $lusakaBranches = DB::table('branches')->where('name', 'like', '%Lusaka%')->pluck('id');
        $staffUsers = DB::table('staffs')->whereIn('branch_id', $lusakaBranches)
            ->where('is_archived', 0)->whereIn('user_id', $eligible)->pluck('user_id');
        foreach ($staffUsers as $userId) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $manage, 'model_type' => 'App\\Models\\User', 'model_id' => $userId,
            ]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Retain granted permissions and booking history on rollback.
    }
};

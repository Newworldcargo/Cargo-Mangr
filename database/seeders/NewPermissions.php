<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NewPermissions extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Insert into permission_groups table
        $permissionGroupId = DB::table('permission_groups')->insertGetId([
            'name' => 'consignment',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $permissionGroupId2 = DB::table('permission_groups')->insertGetId([
            'name' => 'transactions and finance',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $permissionGroupId3 = DB::table('permission_groups')->insertGetId([

            'name' => 'xchange Rates',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Permissions to insert
        $permissions = [
            'update-consignment-tracker',
            'view-consignments',
            'import-consignments',
            'create-consignments',
            'edit-consignments',
            'edit-consignment-code',
            'edit-eta-on-consignment',
            'edit-cargo-date-on-consignment',
            'edit-cargo-source-on-consignment',
            'edit-cargo-destination-on-consignment',
            'edit-cargo-type-on-consignment',
            'delete-consignments',
        ];
        $permissions2 = [
            'create-shipment',
            'edit-shipment-invoices',
            'delete-shipment-invoices',
            'export-shipment-invoices',
            'view-shipment-invoices',
            'confirm-shipment-payment',
            'collect-cross-branch-payments',
            'print-shipment-invoice',
            'print-shipment-receipt',
            'refund-shipment-payment',
        ];
        $permissions3 = [
            'view-exchange-rates',
            'edit-exchange-rates',
            'reset-exchange-rates',
        ];

        // Insert permissions
        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission,
                'guard_name' => 'web',
                'permission_group_id' => $permissionGroupId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // Insert permissions
        foreach ($permissions2 as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission,
                'guard_name' => 'web',
                'permission_group_id' => $permissionGroupId2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        // Insert permissions
        foreach ($permissions3 as $permission) {
            DB::table('permissions')->insert([
                'name' => $permission,
                'guard_name' => 'web',
                'permission_group_id' => $permissionGroupId3,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const BRANCH_NAME = 'United Arab Emirates Branch';

    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable()->change();
        });

        if (DB::table('branches')->where('name', self::BRANCH_NAME)->exists()) {
            return;
        }

        $branchId = DB::table('branches')->insertGetId([
            'code' => 0,
            'user_id' => null,
            'type' => 1,
            'name' => self::BRANCH_NAME,
            'email' => 'uae@newworldcargo.com',
            'responsible_name' => 'Unassigned',
            'responsible_mobile' => null,
            'country_code' => '+971',
            'address' => 'Dubai, United Arab Emirates',
            'country_id' => 231,
            'state_id' => 3391,
            'national_id' => null,
            'default_currency' => 'AED',
            'is_archived' => 0,
            'created_by' => 1,
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('branches')->where('id', $branchId)->update(['code' => $branchId]);
    }

    public function down(): void
    {
        DB::table('branches')->where('name', self::BRANCH_NAME)->delete();

        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->nullable(false)->change();
        });
    }
};

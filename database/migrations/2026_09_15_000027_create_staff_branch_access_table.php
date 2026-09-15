<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateStaffBranchAccessTable extends Migration
{
    public function up()
    {
        Schema::create('staff_branch_access', function (Blueprint $table) {
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('branch_id');
            $table->timestamps();

            $table->primary(['staff_id', 'branch_id']);
            $table->foreign('staff_id')->references('id')->on('staffs')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        $now = now();
        DB::table('staffs')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->chunkById(500, function ($staffs) use ($now) {
                DB::table('staff_branch_access')->insertOrIgnore(
                    $staffs->map(fn ($staff) => [
                        'staff_id' => $staff->id,
                        'branch_id' => $staff->branch_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down()
    {
        Schema::dropIfExists('staff_branch_access');
    }
}

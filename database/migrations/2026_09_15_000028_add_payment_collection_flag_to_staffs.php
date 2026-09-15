<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentCollectionFlagToStaffs extends Migration
{
    public function up()
    {
        Schema::table('staffs', function (Blueprint $table) {
            $table->boolean('can_collect_payments')->default(true)->after('branch_id');
        });
    }

    public function down()
    {
        Schema::table('staffs', function (Blueprint $table) {
            $table->dropColumn('can_collect_payments');
        });
    }
}

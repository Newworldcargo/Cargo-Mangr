<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCashierUserIdToTransxnsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('transxns') && !Schema::hasColumn('transxns', 'cashier_user_id')) {
            Schema::table('transxns', function (Blueprint $table) {
                $table->unsignedBigInteger('cashier_user_id')->nullable()->index()->after('shipment_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('transxns') && Schema::hasColumn('transxns', 'cashier_user_id')) {
            Schema::table('transxns', function (Blueprint $table) {
                $table->dropIndex(['cashier_user_id']);
                $table->dropColumn('cashier_user_id');
            });
        }
    }
}

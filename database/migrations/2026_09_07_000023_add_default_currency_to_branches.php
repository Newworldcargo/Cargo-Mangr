<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDefaultCurrencyToBranches extends Migration
{
    public function up()
    {
        if (Schema::hasTable('branches') && !Schema::hasColumn('branches', 'default_currency')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->string('default_currency', 3)->nullable()->after('state_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'default_currency')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('default_currency');
            });
        }
    }
}

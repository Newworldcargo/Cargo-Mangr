<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPortalRevisionToShipments extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('shipments', 'revision')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->unsignedBigInteger('revision')->default(1)->after('updated_at');
                $table->index(['client_id', 'revision']);
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('shipments', 'revision')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropIndex(['client_id', 'revision']);
                $table->dropColumn('revision');
            });
        }
    }
}

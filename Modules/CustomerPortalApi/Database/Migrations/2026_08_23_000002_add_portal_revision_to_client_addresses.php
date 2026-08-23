<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPortalRevisionToClientAddresses extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('client_addresses', 'revision')) {
            Schema::table('client_addresses', function (Blueprint $table) {
                $table->unsignedBigInteger('revision')->default(1)->after('updated_at');
                $table->index(['client_id', 'revision']);
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('client_addresses', 'revision')) {
            Schema::table('client_addresses', function (Blueprint $table) {
                $table->dropIndex(['client_id', 'revision']);
                $table->dropColumn('revision');
            });
        }
    }
}

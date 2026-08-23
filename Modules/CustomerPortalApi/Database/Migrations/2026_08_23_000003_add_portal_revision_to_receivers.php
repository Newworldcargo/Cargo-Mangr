<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPortalRevisionToReceivers extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('receivers', 'revision')) {
            Schema::table('receivers', function (Blueprint $table) {
                $table->unsignedBigInteger('revision')->default(1)->after('updated_at');
                $table->index(['user_id', 'revision']);
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('receivers', 'revision')) {
            Schema::table('receivers', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'revision']);
                $table->dropColumn('revision');
            });
        }
    }
}

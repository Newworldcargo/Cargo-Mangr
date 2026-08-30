<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddImpersonatorIdToAuditLogsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('audit_logs') || Schema::hasColumn('audit_logs', 'impersonator_id')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('impersonator_id')->nullable()->index()->after('user_id');
        });
    }

    public function down()
    {
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'impersonator_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex(['impersonator_id']);
                $table->dropColumn('impersonator_id');
            });
        }
    }
}

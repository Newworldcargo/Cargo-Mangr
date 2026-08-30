<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBranchIdToAuditLogsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('audit_logs') || Schema::hasColumn('audit_logs', 'branch_id')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->index()->after('consignment_id');
        });
    }

    public function down()
    {
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'branch_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
}

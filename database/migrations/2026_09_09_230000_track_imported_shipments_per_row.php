<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('consignment_import_rows')) return;

        Schema::table('consignment_import_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('consignment_import_rows', 'shipment_id')) {
                $table->unsignedBigInteger('shipment_id')->nullable()->index()->after('included');
            }
            if (!Schema::hasColumn('consignment_import_rows', 'import_action')) {
                $table->string('import_action', 20)->nullable()->after('shipment_id');
            }
            if (!Schema::hasColumn('consignment_import_rows', 'removed_at')) {
                $table->timestamp('removed_at')->nullable()->after('import_action');
            }
            if (!Schema::hasColumn('consignment_import_rows', 'removed_by')) {
                $table->unsignedBigInteger('removed_by')->nullable()->after('removed_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('consignment_import_rows')) return;

        foreach (['removed_by', 'removed_at', 'import_action', 'shipment_id'] as $column) {
            if (Schema::hasColumn('consignment_import_rows', $column)) {
                Schema::table('consignment_import_rows', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};

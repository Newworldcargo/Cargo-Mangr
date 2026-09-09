<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('consignment_import_batches', function (Blueprint $table) {
            $table->date('consignment_date')->nullable()->after('consignment_status');
        });

        DB::table('consignment_import_batches')
            ->whereNull('consignment_date')
            ->update(['consignment_date' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('consignment_import_batches', function (Blueprint $table) {
            $table->dropColumn('consignment_date');
        });
    }
};

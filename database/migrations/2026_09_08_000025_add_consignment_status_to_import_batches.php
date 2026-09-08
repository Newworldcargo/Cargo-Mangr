<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('consignment_import_batches', function (Blueprint $table) {
            $table->string('consignment_status', 20)->nullable()->after('consignment_code');
        });
    }

    public function down(): void
    {
        Schema::table('consignment_import_batches', function (Blueprint $table) {
            $table->dropColumn('consignment_status');
        });
    }
};

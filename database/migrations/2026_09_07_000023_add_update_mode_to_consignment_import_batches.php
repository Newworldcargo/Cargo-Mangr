<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('consignment_import_batches', function (Blueprint $table) {
            $table->string('mode', 20)->default('create')->after('destination_branch_id');
            $table->unsignedBigInteger('target_consignment_id')->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('consignment_import_batches', function (Blueprint $table) {
            $table->dropColumn(['mode', 'target_consignment_id']);
        });
    }
};

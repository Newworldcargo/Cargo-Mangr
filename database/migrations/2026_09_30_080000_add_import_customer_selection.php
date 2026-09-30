<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('consignment_import_rows', function (Blueprint $table) {
            $table->unsignedBigInteger('selected_customer_id')->nullable();
            $table->unsignedBigInteger('customer_selected_by')->nullable();
            $table->timestamp('customer_selected_at')->nullable();
            $table->string('customer_source_hash', 64)->nullable();
            $table->unsignedInteger('customer_selection_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('consignment_import_rows', function (Blueprint $table) {
            $table->dropColumn(['selected_customer_id', 'customer_selected_by', 'customer_selected_at', 'customer_source_hash', 'customer_selection_version']);
        });
    }
};

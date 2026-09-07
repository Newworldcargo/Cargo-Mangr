<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consignment_import_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('created_by')->index();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('shipment_type', 10);
            $table->string('selected_sheet')->nullable();
            $table->unsignedInteger('header_row')->nullable();
            $table->unsignedInteger('data_start_row')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('from_country_id')->nullable();
            $table->unsignedBigInteger('from_state_id')->nullable();
            $table->unsignedBigInteger('to_country_id')->nullable();
            $table->unsignedBigInteger('to_state_id')->nullable();
            $table->json('mappings')->nullable();
            $table->json('summary')->nullable();
            $table->json('result')->nullable();
            $table->string('status')->default('preview');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consignment_import_rows', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id')->index();
            $table->string('sheet_name');
            $table->unsignedInteger('spreadsheet_row');
            $table->json('raw_values');
            $table->json('mapped_values')->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->string('status')->default('pending')->index();
            $table->boolean('included')->default(true);
            $table->timestamps();
            $table->unique(['batch_id', 'sheet_name', 'spreadsheet_row'], 'consignment_import_sheet_row_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_import_rows');
        Schema::dropIfExists('consignment_import_batches');
    }
};

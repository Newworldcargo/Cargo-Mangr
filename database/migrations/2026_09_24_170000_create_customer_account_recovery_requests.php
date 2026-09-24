<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_account_recovery_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('reference')->unique();
            $table->text('encrypted_details');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_account_recovery_requests');
    }
};

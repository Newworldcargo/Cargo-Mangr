<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('shipment_payment_receipts') || Schema::hasColumn('shipment_payment_receipts', 'status')) return;
        Schema::table('shipment_payment_receipts', function (Blueprint $table) {
            $table->string('status')->default('active')->after('amount');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('shipment_payment_receipts') || !Schema::hasColumn('shipment_payment_receipts', 'status')) return;
        Schema::table('shipment_payment_receipts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transxns', function (Blueprint $table) {
            if (!Schema::hasColumn('transxns', 'currency')) {
                $table->string('currency', 10)->nullable()->after('total');
            }
        });

        Schema::table('shipment_payment_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('shipment_payment_receipts', 'currency')) {
                $table->string('currency', 10)->nullable()->after('amount');
            }
        });

        Schema::table('nwc_receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('nwc_receipts', 'payment_currency')) {
                $table->string('payment_currency', 10)->nullable()->after('bill_kwacha');
            }
        });

        Schema::table('shipment_charge_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('shipment_charge_lines', 'currency')) {
                $table->string('currency', 10)->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipment_charge_lines', function (Blueprint $table) {
            if (Schema::hasColumn('shipment_charge_lines', 'currency')) {
                $table->dropColumn('currency');
            }
        });

        Schema::table('nwc_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('nwc_receipts', 'payment_currency')) {
                $table->dropColumn('payment_currency');
            }
        });

        Schema::table('shipment_payment_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('shipment_payment_receipts', 'currency')) {
                $table->dropColumn('currency');
            }
        });

        Schema::table('transxns', function (Blueprint $table) {
            if (Schema::hasColumn('transxns', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};

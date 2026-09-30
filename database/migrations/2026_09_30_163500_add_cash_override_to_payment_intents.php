<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCashOverrideToPaymentIntents extends Migration
{
    public function up()
    {
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->timestamp('cash_override_at')->nullable();
            $table->unsignedBigInteger('cash_override_by')->nullable();
            $table->string('cash_override_reason', 500)->nullable();
        });
    }

    public function down()
    {
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->dropColumn(['cash_override_at', 'cash_override_by', 'cash_override_reason']);
        });
    }
}

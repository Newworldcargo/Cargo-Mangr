<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupersededByToPaymentIntents extends Migration
{
    public function up()
    {
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->unsignedBigInteger('superseded_by')->nullable();
        });
    }

    public function down()
    {
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->dropColumn('superseded_by');
        });
    }
}

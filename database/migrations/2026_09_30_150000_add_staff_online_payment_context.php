<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStaffOnlinePaymentContext extends Migration
{
    public function up()
    {
        Schema::table('transxns', function (Blueprint $table) { $table->json('online_payment_details')->nullable(); });
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->json('billing_snapshot')->nullable();
        });
    }
    public function down()
    {
        Schema::table('transxns', function (Blueprint $table) { $table->dropColumn('online_payment_details'); });
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) { $table->dropColumn(['initiated_by', 'billing_snapshot']); });
    }
}

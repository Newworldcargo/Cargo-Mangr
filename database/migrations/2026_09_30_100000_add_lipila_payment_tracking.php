<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLipilaPaymentTracking extends Migration
{
    public function up()
    {
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('settled_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropColumn(['provider', 'checkout_url', 'last_checked_at', 'settled_at']);
        });
    }
}

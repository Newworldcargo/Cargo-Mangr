<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShippingTariffs extends Migration
{
    public function up()
    {
        Schema::create('billing_shipping_tariffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->unique();
            $table->unsignedBigInteger('branch_id')->index();
            $table->json('configuration');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::table('billing_invoice_items', function (Blueprint $table) {
            $table->json('pricing_snapshot')->nullable();
        });
    }

    public function down()
    {
        Schema::table('billing_invoice_items', function (Blueprint $table) {
            $table->dropColumn('pricing_snapshot');
        });
        Schema::dropIfExists('billing_shipping_tariffs');
    }
}

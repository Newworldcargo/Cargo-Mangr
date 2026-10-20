<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalesShipmentDetails extends Migration
{
    public function up()
    {
        foreach (['billing_invoices', 'billing_quotations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('shipment_details')->nullable();
            });
        }
    }

    public function down()
    {
        throw new RuntimeException('Sales document history must be retained.');
    }
}

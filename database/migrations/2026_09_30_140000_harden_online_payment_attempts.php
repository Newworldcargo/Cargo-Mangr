<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenOnlinePaymentAttempts extends Migration
{
    public function up()
    {
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->unsignedBigInteger('shipment_id')->nullable()->index();
            $table->string('request_key', 64)->nullable();
            $table->string('request_fingerprint', 64)->nullable();
            $table->string('provider_environment', 100)->nullable();
            $table->string('review_reason')->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable()->unique();
            $table->unique(['client_id', 'request_key'], 'payment_client_request_unique');
        });
        DB::table('customer_portal_payment_intents')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('customer_portal_payment_intents')->where('id', $row->id)->update([
                    'shipment_id' => DB::table('transxns')->where('id', $row->invoice_id)->value('shipment_id'),
                    'provider_environment' => $row->provider === 'lipila' ? config('lipila.base_url') : null,
                ]);
            }
        });
    }

    public function down()
    {
        Schema::table('customer_portal_payment_intents', function (Blueprint $table) {
            $table->dropUnique('payment_client_request_unique');
            $table->dropUnique(['receipt_id']);
            $table->dropIndex(['shipment_id']);
            $table->dropColumn(['shipment_id', 'request_key', 'request_fingerprint', 'provider_environment', 'review_reason', 'receipt_id']);
        });
    }
}

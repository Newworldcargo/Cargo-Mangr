<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentWebhookEvents extends Migration
{
    public function up()
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('provider', 30);
            $table->string('event_hash', 64);
            $table->string('payload_hash', 64);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_hash']);
        });
    }

    public function down() { Schema::dropIfExists('payment_webhook_events'); }
}

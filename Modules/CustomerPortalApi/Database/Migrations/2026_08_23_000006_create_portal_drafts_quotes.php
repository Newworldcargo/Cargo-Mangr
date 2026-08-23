<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortalDraftsQuotes extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('customer_portal_quotes')) {
            Schema::create('customer_portal_quotes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedBigInteger('draft_id')->nullable();
                $table->string('transport_mode', 20);
                $table->string('delivery_option', 50);
                $table->json('snapshot');
                $table->string('currency', 3)->default('USD');
                $table->unsignedBigInteger('amount_minor');
                $table->json('assumptions')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamp('expires_at');
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
                $table->index(['client_id', 'status', 'expires_at']);
            });
        }

        if (!Schema::hasTable('customer_portal_shipment_drafts')) {
            Schema::create('customer_portal_shipment_drafts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->string('status', 30)->default('draft');
                $table->json('payload');
                $table->unsignedBigInteger('quote_id')->nullable();
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->string('idempotency_key', 120)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
                $table->index(['client_id', 'status']);
                $table->unique(['client_id', 'idempotency_key']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('customer_portal_shipment_drafts');
        Schema::dropIfExists('customer_portal_quotes');
    }
}

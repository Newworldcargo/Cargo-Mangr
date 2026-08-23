<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortalFilesPaymentsIdempotency extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('customer_portal_files')) {
            Schema::create('customer_portal_files', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('file_id')->unique();
                $table->unsignedInteger('client_id');
                $table->string('purpose', 40);
                $table->string('storage_key');
                $table->string('original_name');
                $table->string('content_type', 150);
                $table->unsignedBigInteger('size_bytes');
                $table->string('checksum', 128)->nullable();
                $table->string('status', 30)->default('pending_upload');
                $table->timestamp('expires_at');
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
                $table->index(['client_id', 'purpose', 'status']);
            });
        }

        if (!Schema::hasTable('customer_portal_payment_intents')) {
            Schema::create('customer_portal_payment_intents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('intent_id')->unique();
                $table->unsignedInteger('client_id');
                $table->unsignedBigInteger('invoice_id');
                $table->string('method', 30);
                $table->string('status', 30)->default('requires_action');
                $table->string('provider_reference')->nullable();
                $table->text('client_token')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->unsignedBigInteger('amount_minor');
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
                $table->index(['client_id', 'status']);
            });
        }

        if (!Schema::hasTable('customer_portal_idempotency')) {
            Schema::create('customer_portal_idempotency', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->string('operation', 100);
                $table->string('idempotency_key', 120);
                $table->string('fingerprint', 128);
                $table->unsignedSmallInteger('response_status')->nullable();
                $table->json('response_body')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->unique(['client_id', 'operation', 'idempotency_key'], 'portal_idem_scope_key');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('customer_portal_idempotency');
        Schema::dropIfExists('customer_portal_payment_intents');
        Schema::dropIfExists('customer_portal_files');
    }
}

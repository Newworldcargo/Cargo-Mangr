<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerPortalWalletsTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('customer_portal_wallets')) {
            Schema::create('customer_portal_wallets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id')->unique();
                $table->string('currency', 3)->default('USD');
                $table->string('status')->default('active');
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_portal_wallet_ledger')) {
            Schema::create('customer_portal_wallet_ledger', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('wallet_id');
                $table->bigInteger('amount_minor');
                $table->string('bucket', 20)->default('available');
                $table->string('type', 50);
                $table->string('status', 30)->default('posted');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['wallet_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('customer_portal_wallet_ledger');
        Schema::dropIfExists('customer_portal_wallets');
    }
}

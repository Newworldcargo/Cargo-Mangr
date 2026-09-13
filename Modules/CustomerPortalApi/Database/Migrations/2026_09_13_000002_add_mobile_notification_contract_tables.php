<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMobileNotificationContractTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('customer_portal_notification_preferences')) {
            Schema::create('customer_portal_notification_preferences', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id')->unique();
                $table->boolean('shipment_updates')->default(true);
                $table->boolean('bill_updates')->default(true);
                $table->boolean('marketing')->default(false);
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('customer_portal_push_tokens')) {
            Schema::create('customer_portal_push_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedBigInteger('user_id');
                $table->string('provider', 30)->default('expo');
                $table->string('platform', 30)->nullable();
                $table->string('token_hash', 64)->unique();
                $table->text('push_token');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'revoked_at'], 'portal_push_tokens_client_active_idx');
                $table->index(['user_id', 'revoked_at'], 'portal_push_tokens_user_active_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('customer_portal_push_tokens');
        Schema::dropIfExists('customer_portal_notification_preferences');
    }
}

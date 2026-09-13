<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMobileCustomerContractTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('customer_portal_payment_methods')) {
            Schema::create('customer_portal_payment_methods', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->string('method', 30);
                $table->string('label')->nullable();
                $table->string('detail')->nullable();
                $table->string('provider_token')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamp('archived_at')->nullable();
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();

                $table->index(['client_id', 'archived_at'], 'portal_pay_methods_client_archived_idx');
                $table->index(['client_id', 'is_default'], 'portal_pay_methods_client_default_idx');
            });
        }

        if (!Schema::hasTable('customer_portal_invoice_preferences')) {
            Schema::create('customer_portal_invoice_preferences', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedBigInteger('invoice_id');
                $table->boolean('reminder_enabled')->default(false);
                $table->timestamp('disputed_at')->nullable();
                $table->string('dispute_status')->nullable();
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();

                $table->unique(['client_id', 'invoice_id'], 'portal_invoice_pref_unique');
                $table->index(['client_id', 'reminder_enabled'], 'portal_invoice_pref_reminder_idx');
            });
        }

        if (!Schema::hasTable('customer_portal_account_preferences')) {
            Schema::create('customer_portal_account_preferences', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id')->unique();
                $table->boolean('marketing_enabled')->default(false);
                $table->timestamp('data_export_requested_at')->nullable();
                $table->timestamp('deletion_requested_at')->nullable();
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('customer_portal_bff_sessions')) {
            Schema::table('customer_portal_bff_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_portal_bff_sessions', 'device_label')) {
                    $table->string('device_label')->nullable()->after('user_agent');
                }
                if (!Schema::hasColumn('customer_portal_bff_sessions', 'trusted_at')) {
                    $table->timestamp('trusted_at')->nullable()->after('revoked_at');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('customer_portal_bff_sessions')) {
            Schema::table('customer_portal_bff_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('customer_portal_bff_sessions', 'device_label')) {
                    $table->dropColumn('device_label');
                }
                if (Schema::hasColumn('customer_portal_bff_sessions', 'trusted_at')) {
                    $table->dropColumn('trusted_at');
                }
            });
        }

        Schema::dropIfExists('customer_portal_account_preferences');
        Schema::dropIfExists('customer_portal_invoice_preferences');
        Schema::dropIfExists('customer_portal_payment_methods');
    }
}

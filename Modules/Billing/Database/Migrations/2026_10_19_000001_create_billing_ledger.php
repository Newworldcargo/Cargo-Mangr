<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBillingLedger extends Migration
{
    public function up()
    {
        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('request_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('number', 40)->nullable()->unique();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->string('status', 24)->default('draft');
            $table->char('currency', 3);
            $table->date('issue_date');
            $table->date('due_date');
            $table->json('customer_snapshot');
            $table->json('issuer_snapshot');
            $table->text('terms')->nullable();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor');
            $table->unsignedBigInteger('tax_minor');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'due_date']);
        });
        Schema::create('billing_invoice_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedInteger('position');
            $table->string('description', 500);
            $table->unsignedInteger('quantity_hundredths');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor');
            $table->unsignedInteger('tax_basis_points');
            $table->unsignedBigInteger('tax_minor');
            $table->unsignedBigInteger('total_minor');
            $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('restrict');
            $table->unique(['invoice_id', 'position']);
        });
        Schema::create('billing_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->uuid('request_key')->unique();
            $table->char('request_fingerprint', 64);
            $table->string('receipt_number', 60)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('method', 24);
            $table->string('reference', 120)->nullable();
            $table->date('paid_on');
            $table->unsignedBigInteger('recorded_by');
            $table->unsignedBigInteger('balance_after_minor');
            $table->timestamps();
            $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('restrict');
        });
        Schema::create('billing_sequences', function (Blueprint $table) {
            $table->unsignedInteger('year')->primary();
            $table->unsignedBigInteger('value')->default(0);
        });
        Schema::create('billing_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('event', 50);
            $table->json('details');
            $table->timestamp('created_at');
            $table->foreign('invoice_id')->references('id')->on('billing_invoices')->onDelete('restrict');
        });
    }

    public function down()
    {
        throw new RuntimeException('Billing records require an explicit retention and rollback plan; automatic deletion is disabled.');
    }
}

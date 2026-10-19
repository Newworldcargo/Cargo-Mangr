<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalesDocuments extends Migration
{
    public function up()
    {
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });
        Schema::create('billing_quotations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('request_key')->unique();
            $table->char('fingerprint', 64);
            $table->string('number', 40)->nullable()->unique();
            $table->unsignedBigInteger('client_id')->index();
            $table->unsignedBigInteger('branch_id')->index();
            $table->unsignedBigInteger('created_by');
            $table->string('status', 24)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->char('currency', 3);
            $table->date('issue_date');
            $table->date('due_date');
            $table->json('customer_snapshot');
            $table->json('issuer_snapshot');
            $table->json('input');
            $table->json('items');
            $table->text('terms')->nullable();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor');
            $table->unsignedBigInteger('tax_minor');
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('converted_invoice_id')->nullable()->unique();
            $table->foreign('converted_invoice_id')->references('id')->on('billing_invoices')->onDelete('restrict');
            $table->timestamps();
        });
        Schema::create('billing_quotation_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('quotation_id');
            $table->foreign('quotation_id')->references('id')->on('billing_quotations')->onDelete('restrict');
            $table->unsignedBigInteger('actor_id');
            $table->string('event', 50);
            $table->json('details');
            $table->timestamp('created_at');
        });
    }

    public function down()
    {
        throw new RuntimeException('Sales records require an explicit retention and rollback plan.');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

class ExtendSalesInvoicing extends Migration
{
    public function up()
    {
        foreach (['billing_invoices', 'billing_quotations'] as $name) {
            Schema::table($name, function (Blueprint $table) { $table->json('document_details')->nullable(); });
        }
        Schema::table('billing_invoice_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity_thousandths')->nullable();
            $table->unsignedBigInteger('input_discount_minor')->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('tax_mode', 12)->nullable();
            $table->string('tax_category', 24)->nullable();
            $table->string('tax_label', 100)->nullable();
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
        });
        Schema::create('billing_profiles', function (Blueprint $table) {
            $table->bigIncrements('id'); $table->unsignedBigInteger('branch_id')->unique();
            $table->string('legal_name', 160); $table->text('address'); $table->string('tpin', 40)->nullable();
            $table->string('vat_status', 24); $table->text('payment_instructions')->nullable(); $table->timestamps();
        });
        Schema::create('billing_tax_rates', function (Blueprint $table) {
            $table->bigIncrements('id'); $table->unsignedBigInteger('branch_id')->index();
            $table->string('label', 100); $table->string('category', 24); $table->unsignedInteger('basis_points');
            $table->date('effective_from'); $table->date('effective_until')->nullable();
            $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('billing_services', function (Blueprint $table) {
            $table->bigIncrements('id'); $table->unsignedBigInteger('branch_id')->index();
            $table->string('name', 160); $table->string('unit', 20); $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3); $table->unsignedBigInteger('tax_id'); $table->string('tax_mode', 12);
            $table->boolean('active')->default(true); $table->timestamps();
        });
        if (Schema::hasTable('permissions')) {
            $group = DB::table('permission_groups')->where('name', 'sales')->value('id');
            DB::table('permissions')->insertOrIgnore(['name'=>'manage-billing-settings','guard_name'=>'web','permission_group_id'=>$group,'created_at'=>now(),'updated_at'=>now()]);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down() { throw new RuntimeException('Sales history must be retained.'); }
}

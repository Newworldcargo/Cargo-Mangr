<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesDeliveryRecords extends Migration
{
    public function up()
    {
        Schema::create('billing_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 20);
            $table->unsignedBigInteger('document_id');
            $table->longText('pdf_base64');
            $table->char('sha256', 64);
            $table->timestamp('created_at');
            $table->unique(['type', 'document_id']);
        });
        Schema::create('billing_deliveries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('request_key')->unique();
            $table->string('type', 20);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('recipient');
            $table->string('purpose', 20);
            $table->string('status', 20)->default('pending');
            $table->timestamp('send_at');
            $table->timestamp('accepted_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
            $table->index(['type', 'document_id']);
        });
    }

    public function down()
    {
        throw new RuntimeException('Document and delivery history require an explicit retention plan.');
    }
}

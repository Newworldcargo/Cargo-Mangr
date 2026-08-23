<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePortalSupportReturnsPickups extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('supports', 'portal_revision')) {
            Schema::table('supports', function (Blueprint $table) {
                $table->unsignedBigInteger('portal_revision')->default(1)->after('updated_at');
                $table->index(['user_id', 'portal_revision']);
            });
        }

        if (!Schema::hasTable('customer_portal_returns')) {
            Schema::create('customer_portal_returns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedBigInteger('shipment_id');
                $table->text('reason');
                $table->string('handover', 20);
                $table->string('status', 30)->default('requested');
                $table->string('display_status', 100)->default('Requested');
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
                $table->index(['client_id', 'status']);
                $table->index(['shipment_id', 'status']);
            });
        }

        if (!Schema::hasTable('customer_portal_pickups')) {
            Schema::create('customer_portal_pickups', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('client_id');
                $table->unsignedBigInteger('shipment_id')->nullable();
                $table->string('status', 30)->default('requested');
                $table->string('collection_point');
                $table->date('scheduled_date')->nullable();
                $table->string('scheduled_time')->nullable();
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestamps();
                $table->index(['client_id', 'status']);
                $table->index(['shipment_id', 'status']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('customer_portal_pickups');
        Schema::dropIfExists('customer_portal_returns');
        if (Schema::hasColumn('supports', 'portal_revision')) {
            Schema::table('supports', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'portal_revision']);
                $table->dropColumn('portal_revision');
            });
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('address');
            $table->unsignedBigInteger('state_id')->nullable()->after('country_id');
        });
        Schema::table('consignment_import_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('pickup_branch_id')->nullable()->after('default_destination');
            $table->unsignedBigInteger('destination_branch_id')->nullable()->after('pickup_branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('consignment_import_batches', function (Blueprint $table) {
            $table->dropColumn(['pickup_branch_id', 'destination_branch_id']);
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['country_id', 'state_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consignment_import_rows')
            && !Schema::hasColumn('consignment_import_rows', 'phone_override_2')) {
            Schema::table('consignment_import_rows', function (Blueprint $table) {
                $table->string('phone_override_2', 32)->nullable()->after('phone_override');
            });
        }

        if (Schema::hasTable('clients') && !Schema::hasColumn('clients', 'secondary_mobile')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('secondary_mobile')->nullable()->after('responsible_mobile');
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'secondary_mobile')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('secondary_mobile')->nullable()->after('responsible_mobile');
            });
        }

        if (Schema::hasTable('shipments')) {
            if (!Schema::hasColumn('shipments', 'client_phone_2')) Schema::table('shipments', function (Blueprint $table) {
                $table->text('client_phone_2')->nullable()->after('client_phone');
            });
            if (!Schema::hasColumn('shipments', 'reciver_phone_2')) Schema::table('shipments', function (Blueprint $table) {
                $table->text('reciver_phone_2')->nullable()->after('reciver_phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shipments')) {
            if (Schema::hasColumn('shipments', 'reciver_phone_2')) Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('reciver_phone_2');
            });
            if (Schema::hasColumn('shipments', 'client_phone_2')) Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('client_phone_2');
            });
        }
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'secondary_mobile')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('secondary_mobile');
            });
        }
        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'secondary_mobile')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('secondary_mobile');
            });
        }
        if (Schema::hasTable('consignment_import_rows')
            && Schema::hasColumn('consignment_import_rows', 'phone_override_2')) {
            Schema::table('consignment_import_rows', function (Blueprint $table) {
                $table->dropColumn('phone_override_2');
            });
        }
    }
};

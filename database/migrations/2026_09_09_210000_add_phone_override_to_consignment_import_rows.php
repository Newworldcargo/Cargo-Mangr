<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('consignment_import_rows')
            && !Schema::hasColumn('consignment_import_rows', 'phone_override')) {
            Schema::table('consignment_import_rows', function (Blueprint $table) {
                $table->string('phone_override', 32)->nullable()->after('mapped_values');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('consignment_import_rows')
            && Schema::hasColumn('consignment_import_rows', 'phone_override')) {
            Schema::table('consignment_import_rows', function (Blueprint $table) {
                $table->dropColumn('phone_override');
            });
        }
    }
};

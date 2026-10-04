<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddGlobalSearchIndexes extends Migration
{
    public function up()
    {
        if (DB::getDriverName() === 'mysql') DB::statement('SET SESSION lock_wait_timeout = 5');
        foreach (['client_phone', 'client_phone_2'] as $field) {
            $column = $field . '_search';
            $expression = "COALESCE($field, '')";
            foreach ([' ', '+', '-', '(', ')', '.'] as $character) $expression = "REPLACE($expression, '$character', '')";
            $expression = "SUBSTR($expression, 1, 255)";
            if (!Schema::hasColumn('shipments', $column)) {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE shipments ADD COLUMN $column VARCHAR(255) GENERATED ALWAYS AS ($expression) VIRTUAL, ALGORITHM=INPLACE, LOCK=NONE");
                } else {
                    Schema::table('shipments', fn (Blueprint $table) => $table->string($column)->virtualAs($expression));
                }
            }
        }
        foreach (['shipments' => ['code', 'client_phone_search', 'client_phone_2_search'], 'clients' => ['name', 'email']] as $table => $columns) {
            foreach ($columns as $column) {
                $name = 'global_search_' . $table . '_' . $column;
                if (DB::getDriverName() === 'mysql') {
                    if (!collect(DB::select("SHOW INDEX FROM $table"))->contains('Key_name', $name)) {
                        DB::statement("ALTER TABLE $table ADD INDEX $name ($column), ALGORITHM=INPLACE, LOCK=NONE");
                    }
                } else {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($column, $name));
                }
            }
        }
    }

    public function down()
    {
        foreach (['shipments' => ['code', 'client_phone_search', 'client_phone_2_search'], 'clients' => ['name', 'email']] as $table => $columns) {
            foreach ($columns as $column) Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex('global_search_' . $table . '_' . $column));
        }
        Schema::table('shipments', fn (Blueprint $table) => $table->dropColumn(['client_phone_search', 'client_phone_2_search']));
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!DB::table('currencies')->where('code', 'AED')->exists()) {
            $rate = DB::table('currency_exchange_rates')
                ->where('from_currency', 'USD')
                ->where('to_currency', 'AED')
                ->value('exchange_rate');

            DB::table('currencies')->insert([
                'name' => 'United Arab Emirates Dirham',
                'symbol' => 'د.إ',
                'exchange_rate' => $rate ?: 1,
                'default' => 0,
                'status' => 1,
                'code' => 'AED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep shared reference data if application migrations are rolled back.
    }
};

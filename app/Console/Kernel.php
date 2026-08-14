<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\ReplaceDatabase::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('media-library:delete-old-temporary-uploads')->daily();
        // Refresh exchange rates daily (floatrates, fallback open.er-api) so rates never need manual updating
        $schedule->command('rates:sync')->dailyAt('02:00');
        // Intraday refresh every 10 minutes during Zambian trading hours (06:30-19:30 CAT = Africa/Lusaka)
        $schedule->command('rates:sync --mode=intraday')->everyTenMinutes()->between('6:30', '19:30')->timezone('Africa/Lusaka')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

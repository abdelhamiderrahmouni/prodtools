<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ComponentGetterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'component:get {name : The name of the component you want to get.}
                                          {--store-path= : the path where to store the component, by default it\'s "resources\views\components"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get a component from the server';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}

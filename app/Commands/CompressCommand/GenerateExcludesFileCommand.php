<?php

namespace App\Commands\CompressCommand;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class GenerateExcludesFileCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compress:generate-excludes-file {path? : The path to the folder to compress}
                                                            {--defaults= : Directories and files to exclude from the zip}
                                                            {--force : Overwrite the existing excludes file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the excludes file for the compress command.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->argument('path') ?? getcwd();

        // lookup if .prodtools_compress_excludes exists
        if (file_exists($path . '/.prodtools_compress_excludes') && ! $this->option('force')) {
            $this->error('The excludes file already exists, use --force to overwrite it.');
            return;
        }

        $this->info('Generating the excludes file...');

        $excludes = $this->option('defaults')
            ? explode(',', $this->option('defaults'))
            : [
                '.prodtools_compress_excludes',
                'node_modules',
                '.git',
                'database/database.sqlite',
                '.github',
                '.idea',
            ];

        // write to .prodtools_compress_excludes
        if (file_put_contents(
            $path . '/.prodtools_compress_excludes',
            implode("\n", $excludes)
        ))
        {
            $this->info('Excludes file generated successfully.');
            return;
        }

        $this->error('Failed to generate the excludes file.');
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}

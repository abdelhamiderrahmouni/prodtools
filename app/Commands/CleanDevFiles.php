<?php

namespace App\Commands;

use Illuminate\Console\Command;

class CleanDevFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up development files such as logs, debugbar, and temporary files in laravel applications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up development files...');

        // get current working directory
        $currentPath = getcwd();

        // check if the path is a laravel application
        if (!file_exists($currentPath . '/artisan')) {
            $this->error('This command must be run from the root of a Laravel application.');
            return;
        }

        $pathsToClean = [
            'logs' => $currentPath . '/storage/logs/*.log',
            'debugbar' => $currentPath . '/storage/debugbar/*',
            'livewire-tmp' => $currentPath . '/storage/app/livewire-tmp/*',
            'pail' => $currentPath . '/storage/pail/*',
        ];

        $totalDeleted = 0;

        foreach ($pathsToClean as $type => $path) {
            $count = $this->cleanFiles($path);
            $this->info("Deleted {$count} {$type} files");
            $totalDeleted += $count;
        }

        $this->info("Total files cleaned: {$totalDeleted}");
        $this->info('Development files cleaned successfully.');
    }

    /**
     * Clean files matching the given pattern, preserving .gitignore
     *
     * @param string $pattern
     * @return int Number of files deleted
     */
    private function cleanFiles(string $pattern): int
    {
        $count = 0;
        $files = glob($pattern, GLOB_NOSORT);

        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitignore') {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}

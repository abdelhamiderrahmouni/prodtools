<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;

class CompressCommand extends Command
{
    protected $signature = 'compress {--exclude= : Directories to exclude from the zip}';

    protected $description = 'Zip your project with ease.';

    public $excludes = [];

    public $projectName = '';

    public $projectPath;

    public $zipPath;

    public function handle()
    {
        $this->projectName = basename(getcwd());

        $this->projectPath = getcwd();

        $this->excludes = $this->getExcludes();

        $zip = $this->initializeZipArchive();

        // Count the total number of files to be processed
        $totalFiles = count(iterator_to_array(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->projectPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            ),
            false
        ));

        // Initialize the progress bar
        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        // Add files to the zip archive
        $this->addFiles($this->projectPath, $zip, $this->excludes, $progressBar);

        // Finish the progress bar
        $progressBar->finish();

        // Close the zip archive
        $zip->close();

        // Provide feedback to the user
        $this->newLine(2);
        $this->info("🥳 Project {$this->projectName} has been successfully zipped to {$this->zipPath}");
    }

    /*
     * Function to add files to the zip archive and update the progress bar
     */
    private function addFiles($dir, $zip, $toExclude, $progressBar): void
    {

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue; // Skip directories
            }

            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($dir) + 1);

            // Check if the file is in an excluded directory
            $shouldExclude = false;
            foreach ($toExclude as $exclude) {
                if (strpos($relativePath, trim($exclude)) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude || ! is_readable($filePath)) {
                continue; // Skip the file if it's in an excluded directory or not readable
            }

            $zip->addFile($filePath, $relativePath);
            $progressBar->advance(); // Advance the progress bar
        }
    }

    private function getExcludes(): array
    {
        return $this->option('exclude')
            ? explode(',', $this->option('exclude'))
            : config('compress.excludes');
    }

    private function initializeZipArchive(): ZipArchive|null
    {
        // Convert folder name to snake case for the zip file name
        $zipFileName = config('compress.output_file_name') ?? strtolower(preg_replace('/(?<!\ )[A-Z]/', '_$0', $this->projectName));

        // Check if the folder exists
        if (! file_exists($this->projectPath)) {
            $this->error("Folder {$this->projectPath} does not exist.");
            return null;
        }

        // Initialize the zip archive
        $zip = new ZipArchive();
        $this->zipPath = "{$zipFileName}.zip";

        if ($zip->open($this->zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Cannot open {$this->zipPath}");
            return null;
        }

        return $zip;
    }
}

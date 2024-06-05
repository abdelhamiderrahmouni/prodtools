<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;

class CompressCommand extends Command
{
    protected $signature = 'compress {path? : The path to the folder to compress}
                                     {--exclude= : Directories to exclude from the zip}
                                     {--output-name|name= : The name of the output zip file}
                                     {--chunk-size=60 : The maximum size of each chunk in MB}';

    protected $description = 'Zip your project with ease and optional chunking.';

    public array $excludes = [];

    public string $projectName = '';

    public string $projectPath;

    public string $zipPath;

    public string|null $outputFileName = null;

    public function handle()
    {
        $this->outputFileName = $this->option('name');
        $this->projectPath = $this->argument('path') ?? getcwd();
        $this->projectName = basename($this->projectPath);
        $this->excludes = $this->getExcludes();
        $chunkSize = $this->option('chunk-size') * 1024 * 1024; // Convert MB to bytes

        $totalFiles = count(iterator_to_array(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->projectPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            ),
            false
        ));

        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        $zipIndex = 0;
        $zip = $this->initializeZipArchive($zipIndex);
        $currentZipSize = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->projectPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($this->projectPath) + 1);

            if ($this->shouldExclude($relativePath) || !is_readable($filePath)) {
                continue;
            }

            $fileSize = filesize($filePath);
            if ($currentZipSize + $fileSize > $chunkSize) {
                $zip->close();
                $zipIndex++;
                $zip = $this->initializeZipArchive($zipIndex);
                $currentZipSize = 0;
            }

            $zip->addFile($filePath, $relativePath);
            $currentZipSize += $fileSize;

            if ($zipIndex > 10)
                exit();

            $progressBar->advance();
        }

        $zip->close();
        $progressBar->finish();

        $this->newLine(2);
        $this->info("🥳 Project {$this->projectName} has been successfully zipped to {$this->outputFileName}*.zip in chunks.");
    }

    private function shouldExclude($relativePath): bool
    {
        foreach ($this->excludes as $exclude) {
            if (strpos($relativePath, trim($exclude)) === 0) {
                return true;
            }
        }
        return false;
    }

    private function getExcludes(): array
    {
        return $this->option('exclude')
            ? explode(',', $this->option('exclude'))
            : config('compress.excludes');
    }

    private function initializeZipArchive($index): ZipArchive|null
    {
        $zipFileName = config('compress.output_file_name')
            ?? $this->outputFileName
            ?? strtolower(preg_replace('/(?<!\ )[A-Z]/', '_$0', $this->projectName));

        $zipFileName .= $index > 0 ? "_part{$index}" : '';
        $this->zipPath = "{$zipFileName}.zip";

        if (!file_exists($this->projectPath)) {
            $this->error("Folder {$this->projectPath} does not exist.");
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($this->zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Cannot open {$this->zipPath}");
            return null;
        }

        return $zip;
    }
}

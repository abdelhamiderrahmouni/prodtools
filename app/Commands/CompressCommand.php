<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;

class CompressCommand extends Command
{
    protected $signature = 'compress {path? : The path to the folder to compress}
                                     {--exclude= : Directories and files to exclude from the zip}
                                     {--include= : Directories and files to make sure they are included in the zip}
                                     {--output-name|name= : The name of the output zip file}
                                     {--chunk-size= : The maximum size of each chunk in MB, 0 for no chunking}
                                     {--excludes_file= : A file containing directories and files to exclude from the zip (should be relative to the project path)}
                                     {--append-excludes= : Directories and files to append to the excludes array or file}';

    protected $description = 'Zip your project with ease and optional chunking.';

    public array $excludes = [];

    public string $projectName = '';

    public string $projectPath;

    public string $zipPath;

    public string|null $outputFileName = null;

    public function handle()
    {
        $this->validateOptions();

        $this->outputFileName = $this->option('name');
        $this->projectPath = $this->argument('path') ?? getcwd();
        $this->projectName = basename($this->projectPath);
        $this->excludes = $this->getExcludes();

        $chunkSize = ($this->option('chunk-size') ?? 99999999) * 1024 * 1024; // Convert MB to bytes

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

        if ($chunkSize === 99999999)
            $this->info("🥳 Project {$this->projectName} has been successfully zipped to {$this->outputFileName}*.zip in chunks.");
        else
            $this->info("🥳 Project {$this->projectName} has been successfully zipped to {$this->outputFileName}.zip.");
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
        $includes = $this->getIncludes();

        $excludesFile = $this->projectPath . '/' . ($this->option('excludes_file') ?? '.prodtools_compress_excludes');

        if(file_exists($excludesFile) && !$this->option('exclude'))
        {
            $excludes = file($excludesFile, FILE_IGNORE_NEW_LINES);

            $excludes = array_merge($excludes, $this->getAppendedExcludes());

            // remove the includes from the excludes
            return array_diff($excludes, $includes);
        }

        if ($this->option('exclude'))
        {
            $excludes = explode(',', $this->option('exclude'));

            $excludes = array_merge($excludes, $this->getAppendedExcludes());

            // remove the includes from the excludes
            return array_diff($excludes, $includes);
        }

        $excludes = array_merge(config('compress.excludes'), $this->getAppendedExcludes());
        // remove the includes from the excludes
        return array_diff($excludes, $includes);
    }

    private function getAppendedExcludes()
    {
        if ($this->option('append-excludes'))
            return explode(',', $this->option('append-excludes'));
    }

    private function getIncludes()
    {
        return $this->option('include')
            ? explode(',', $this->option('include'))
            : [];
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

    private function validateOptions()
    {
        if ($this->option('chunk-size') && !is_numeric($this->option('chunk-size'))) {
            $this->error('Chunk size must be a number.');
            exit();
        }

        if ($this->option('exclude') && $this->option('excludes_file'))
        {
            $this->error('You cannot use both --exclude and --excludes_file options at the same time.');
            exit();
        }
    }
}

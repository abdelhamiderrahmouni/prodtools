<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use ZipArchive;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class CompressCommand extends Command
{
    protected $signature = 'compress {path? : The path to the folder to compress}
                                     {--exclude=* : Directories and files to exclude from the zip}
                                     {--include=* : Directories and files to make sure they are included in the zip}
                                     {--output-name|name= : The name of the output zip file}
                                     {--chunk-size=0 : The maximum size of each chunk in MB, 0 for no chunking}
                                     {--excludes-file= : A file containing directories and files to exclude from the zip (should be relative to the project path)}
                                     {--append-excludes=* : Directories and files to append to the excludes array or file}
                                     {--generate-excludes-file|generate : Generate the excludes file for the compress command}';


    protected $description = 'Zip your project with ease and optional chunking.';

    private array $excludes = [];
    private string $projectPath;
    private string $zipPath;
    private ?string $outputFileName = null;
    private const DEFAULT_CHUNK_SIZE = 2048; // 2GB default chunk size

    public function handle()
    {
        if ($this->option('generate')) {
            $this->call('compress:generate-excludes-file', ['path' => $this->argument('path')]);
            return;
        }

        try {
            $this->validateOptions();
            $this->setupProjectDetails();
            $this->compressProject();
        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            return 1;
        }
    }

    private function validateOptions(): void
    {
        if ($this->option('chunk-size') && !is_numeric($this->option('chunk-size'))) {
            throw new \InvalidArgumentException('Chunk size must be a number.');
        }

        if ($this->option('exclude') && $this->option('excludes-file')) {
            throw new \InvalidArgumentException('You cannot use both --exclude and --excludes-file options at the same time.');
        }
    }

    private function setupProjectDetails(): void
    {
        $this->projectPath = $this->argument('path') ?? getcwd();
        $this->outputFileName = $this->option('name') ?? basename($this->projectPath);
        $this->excludes = $this->getExcludes();

        if (!File::isDirectory($this->projectPath)) {
            throw new \InvalidArgumentException("Folder {$this->projectPath} does not exist.");
        }
    }

    private function compressProject(): void
    {
        $chunkSize = ($this->option('chunk-size') ?: self::DEFAULT_CHUNK_SIZE) * 1024 * 1024; // Convert MB to bytes

        $finder = new Finder();
        $finder->files()->in($this->projectPath);

        foreach ($this->excludes as $exclude) {
            $finder->exclude($exclude);
        }

        $totalFiles = iterator_count($finder);

        $this->info("Starting first pass: Estimating compression ratio...");
        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        // First pass: estimate compression ratios
        $files = [];
        $totalUncompressedSize = 0;
        $tempZip = new ZipArchive();
        $tempZipPath = tempnam(sys_get_temp_dir(), 'temp_zip_');
        $tempZip->open($tempZipPath, ZipArchive::CREATE);

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $this->getRelativePath($filePath);
            $fileSize = $file->getSize();

            $tempZip->addFile($filePath, $relativePath);
            $files[] = ['path' => $filePath, 'relative' => $relativePath, 'size' => $fileSize];
            $totalUncompressedSize += $fileSize;

            $progressBar->advance();
        }

        $tempZip->close();
        $totalCompressedSize = filesize($tempZipPath);
        unlink($tempZipPath);

        $compressionRatio = $totalCompressedSize / $totalUncompressedSize;

        $progressBar->finish();
        $this->newLine();

        // Second pass: create actual zip files
        $this->info("Starting second pass: Creating zip files...");
        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        $zipIndex = 0;
        $currentEstimatedSize = 0;
        $filesToAdd = [];

        $zip = $this->initializeZipArchive($zipIndex);

        foreach ($files as $file) {
            $estimatedCompressedSize = $file['size'] * $compressionRatio;

            if ($currentEstimatedSize + $estimatedCompressedSize > $chunkSize && !empty($filesToAdd)) {
                $this->addFilesToZip($zip, $filesToAdd);
                $this->finalizeZip($zip);
                $zipIndex++;
                $zip = $this->initializeZipArchive($zipIndex);
                $currentEstimatedSize = 0;
                $filesToAdd = [];
            }

            $filesToAdd[] = $file;
            $currentEstimatedSize += $estimatedCompressedSize;

            $progressBar->advance();
        }

        // Add any remaining files
        if (!empty($filesToAdd)) {
            $this->addFilesToZip($zip, $filesToAdd);
            $this->finalizeZip($zip);
        }

        $progressBar->finish();

        $this->newLine(2);

        $successMessage = $zipIndex > 0
            ? "🥳 Project has been successfully zipped to {$this->outputFileName}*.zip in chunks."
            : "🥳 Project has been successfully zipped to {$this->outputFileName}.zip.";

        $this->info($successMessage);

        // Debug information
        $this->newLine();
        $this->info("Debug Information:");
        $this->info("Chunk size: " . $this->formatBytes($chunkSize));
        $this->info("Total files processed: $totalFiles");
        $this->info("Number of zip files created: " . ($zipIndex + 1));
        $this->info("Estimated compression ratio: " . round($compressionRatio, 4));
    }
    private function addFilesToZip(ZipArchive $zip, array $files): void
    {
        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['relative']);
        }
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getExcludes(): array
    {
        $includes = $this->option('include');
        $excludesFile = $this->projectPath . '/' . ($this->option('excludes-file') ?? '.prodtools_compress_excludes');

        if (File::exists($excludesFile) && !$this->option('exclude')) {
            $excludes = File::lines($excludesFile)->map(fn($line) => trim($line))->toArray();
        } elseif ($this->option('exclude')) {
            $excludes = $this->option('exclude');
        } else {
            $excludes = config('compress.excludes', []);
        }

        $excludes = array_merge($excludes, $this->option('append-excludes'));
        return array_diff($excludes, $includes);
    }

    private function initializeZipArchive(int $index): ZipArchive
    {
        $zipFileName = $this->outputFileName . ($index > 0 ? "_part{$index}" : '');
        $this->zipPath = "{$zipFileName}.zip";

        $zip = new ZipArchive();
        if ($zip->open($this->zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot open {$this->zipPath}");
        }

        return $zip;
    }

    private function finalizeZip(ZipArchive $zip): void
    {
        $zip->close();
    }

    private function getRelativePath(string $filePath): string
    {
        return ltrim(str_replace($this->projectPath, '', $filePath), '/');
    }
}

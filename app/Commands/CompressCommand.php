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

        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        $zipIndex = 0;
        $currentZipSize = 0;

        $zip = $this->initializeZipArchive($zipIndex);

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            $relativePath = $this->getRelativePath($filePath);

            $fileSize = $file->getSize();
            if ($currentZipSize + $fileSize > $chunkSize) {
                $this->finalizeZip($zip);
                $zipIndex++;
                $zip = $this->initializeZipArchive($zipIndex);
                $currentZipSize = 0;
            }

            $zip->addFile($filePath, $relativePath);
            $currentZipSize += $fileSize;

            $progressBar->advance();
        }

        $this->finalizeZip($zip);
        $progressBar->finish();

        $this->newLine(2);
        $this->info("🥳 Project has been successfully zipped to {$this->outputFileName}*.zip" . ($zipIndex > 0 ? " in chunks." : "."));
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

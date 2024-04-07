<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\Finder\Finder;

class ImageOptimizerCommand extends Command
{
    protected $signature = 'images:optimize
                            {path? : The path to the assets folder. Default is in config file (public/assets).}
                            {--details : Display the output of the optimization process.}';

    protected $description = 'Compress all static images in the provided folder recursively!';

    protected $errors = [];

    protected $assetsDir = '';

    public function handle(): int
    {
        $this->assetsDir = $this->argument('path') ?? config('image-optimizer-command.assets_path');

        // check if the provided path is a directory
        if (! is_dir($this->assetsDir)) {
            $this->error("The provided path {$this->assetsDir} is not a directory.");

            return self::FAILURE;
        }

        $finder = new Finder();

        $this->info('🔍 Loading images from '.$this->assetsDir.' directory.');

        // Find all files within the assets directory
        $finder->files()->in($this->assetsDir);

        $this->info('🚀 Optimizing images...');
        $this->newLine();

        $bar = $this->output->createProgressBar(count($finder));
        if (! $this->option('details')) {
            $bar->start();
        }

        foreach ($finder as $file) {
            $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            $supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

            if (in_array($extension, $supportedExtensions)) {
                try {
                    $optimizerChain = OptimizerChainFactory::create();
                    $optimizerChain->optimize($file->getPathname());

                    if ($this->option('details')) {
                        $this->info("Optimized: {$file->getPathname()}");
                    } else {
                        $bar->advance();
                    }

                } catch (\Exception $e) {
                    $this->errors[$file->getFilename()] = $e->getMessage();
                }
            }
        }

        if (! $this->option('details')) {
            $bar->finish();
        }

        $this->newLine(2);
        $this->info('🥳 All images in the '.$this->assetsDir.' folder have been optimized.');

        $this->reportErrors();

        return self::SUCCESS;
    }

    protected function reportErrors()
    {
        if (count($this->errors) > 0) {
            $this->error('Some errors occurred during the optimization:');
            foreach ($this->errors as $filename => $error) {
                $this->error("{$filename}: {$error}");
            }
        }
    }
}

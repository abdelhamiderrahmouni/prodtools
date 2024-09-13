<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageOptimizerAdvancedCommand extends Command
{
    protected $signature = 'advanced-optimize:images {--vd|views-dir= : The directory of the views.}
                                                     {--ad|assets-root=* : The root directory of the assets.}
                                                     {--p|pattern= : The pattern to match image paths.}
                                                     {--m|maxDimension= : The max dimension of the image.}
                                                     {--w|webp : add this flag to enable webp conversion.}';

    protected $description = 'Resize images to the desired max dimension and convert them to WebP format.';

    protected $imageManager;

    protected $viewsDir;

    protected $assetsDir;

    public function __construct()
    {
        parent::__construct();
        // Create image manager with the desired driver
        $this->imageManager = new ImageManager(new Driver()); // or 'imagick'
    }

    public function handle()
    {
        // Define the directories to scan for views
        $this->viewsDir = $this->option('views-dir') ?? [resource_path('views')];

        $this->assetsDir = $this->option('assets-root') ?? public_path();

        // Define patterns to match image paths
        $imgPatterns = [
            '/<img[^>]+src="([^">]+)"/',
            '/<source[^>]+srcset="([^">]+)"/',
            '/background-image\s*:\s*url\(([^)]+)\)/'
        ];

        // execute git commit -am
        // exec('git commit -am "making sure the latest changes are commited before starting the image optimization process."');

        foreach ($this->viewsDir as $viewDir) {
            $this->scanAndOptimize($viewDir, $imgPatterns);
        }

        $this->info('Images resized to max 1080px.');
    }

    private function scanAndOptimize($dir, $patterns)
    {
        $files = File::allFiles($dir);

        foreach ($files as $file) {
            // Check if the file is a Blade view
            if ($file->getExtension() === 'php' && strpos($file->getFilename(), '.blade.php') !== false) {
                $content = File::get($file->getRealPath());
                $updatedContent = $content;

                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $content, $matches)) {
                        foreach ($matches[1] as $imagePath) {
                            $imagePath = trim($imagePath, '"\'');
                            $imagePath = ltrim($imagePath, '/');

                            if (File::exists($this->assetsDir . '/' . $imagePath)) {
                                // Resize image if necessary
                                $filePath = $this->assetsDir . '/' . $imagePath;

                                $this->resizeImage($filePath, 1080);

                                // Convert to WebP and get the new path
                                $webpPath = $this->convertToWebP($filePath);

                                // remove the absolute path from the image path
                                $webpPath = str_replace($this->assetsDir, '', $this->assetsDir . $webpPath);

                                // Update the content with the new WebP image path
                                $updatedContent = str_replace($imagePath, $webpPath, $updatedContent);
                            }
                        }
                    }
                }

                if ($updatedContent !== $content) {
                    File::put($file->getRealPath(), $updatedContent);
                }
            }
        }
    }

    private function resizeImage($filePath, $maxDimension)
    {
        $image = $this->imageManager->read($filePath);

        $aspectRadio = $image->width() / $image->height();
        $maxHeight = $maxDimension / $aspectRadio;

        if ($image->width() > $maxDimension || $image->height() > $maxDimension) {
            $image->resize($maxDimension, $maxHeight);
            $image->save($filePath);
        }
    }


    private function convertToWebP($filePath)
    {
        $image = $this->imageManager->read($filePath);

        // Define the path for the WebP image
        $webpPath = pathinfo($filePath, PATHINFO_DIRNAME) . '/' . pathinfo($filePath, PATHINFO_FILENAME) . '.webp';

        // Save the image in WebP format
        $image->toWebp(90)->save($webpPath);

        return $webpPath;
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}

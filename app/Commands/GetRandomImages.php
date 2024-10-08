<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GetRandomImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:get {output-directory=local_images}
                                       {--amount=5}
                                       {--size=200x200}
                                       {--multi-size}
                                       {--keep-old|keep}
                                       {--amounts="5,5"]}
                                       {--sizes="200x200,1280x720"}
                                       {--terms=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get random images stored locally, so that the seed process can be fast.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Use an empty string to get random images
        // We could fine-tune with search terms, examples: 'nature', 'people', 'city', 'abstract', 'food', 'sports', 'technics', 'transport', 'animals'
        // This needs some deeper research to get the best results

        $multiple = $this->option('multi-size');
        $keepOld = $this->option('keep');
        $outputDirectory = $this->argument('output-directory');

        $schemas = [];
        // user provided terms
        $terms = explode(",", $this->option('terms'));

        // if the user wants to get multiple images with different sizes
        if ($multiple){
            // user provided amounts
            $amounts = explode(",", $this->option('amounts'));

            // user provided sizes
            $sizes = explode(",", $this->option('sizes'));

            // if the user has not provided the same amount of sizes as amounts,
            // we will use the default amount for all sizes
            if (count($amounts) !== count($sizes)) {
                $amounts = array_fill(0, count($sizes), $this->option('amount'));
            }

            // create the schema
            $i = 0;
            foreach ($amounts as $amount) {
                $schemas[] = ['amount' => (int) $amount, 'size' => $sizes[$i], 'terms' => $terms];
                $i++;
            }
        } else {

            // if the user wants to get only one image with one size
            $amount = $this->option('amount') ;
            $size = $this->option('size');
            $schemas[] = compact('amount', 'size', 'terms');
        }

        // get the images
        foreach ($schemas as $schema) {
            $this->getRandomImages($schema, $outputDirectory, $keepOld);
        }

        $this->newLine();
        $path = realpath($outputDirectory);
        // show the user the path where the images are stored
        $this->info("Images are stored in: {$path}");
    }

    protected function getRandomImages(array $schema, string $outputDirectory, bool $keepOld): void
    {
        ['amount' => $amount, 'size' => $size, 'terms' => $terms] = $schema;

        $this->comment(PHP_EOL . "Getting $amount random images of size $size, of topic: " . implode(', ', $terms));

        if (! $keepOld) {
            File::deleteDirectory($outputDirectory . DIRECTORY_SEPARATOR . $size);
        }

        $progressBar = $this->output->createProgressBar($amount);
        $progressBar->start();

        foreach (range(1, $amount) as $i) {

            $url = "https://source.unsplash.com/{$size}/?" . implode(',', $terms);
            $image = file_get_contents($url);

            File::ensureDirectoryExists($outputDirectory . DIRECTORY_SEPARATOR . $size);
            $filename = $outputDirectory . DIRECTORY_SEPARATOR . $size . DIRECTORY_SEPARATOR . Str::uuid() . ".jpg";

            File::put(
                path: $filename,
                contents: $image
            );

            $progressBar->advance();
        }

        $progressBar->finish();

        $this->newLine(2);
        $this->info('Done!');
    }
}

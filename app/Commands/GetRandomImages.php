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
            $this->getRandomImages($schema, $outputDirectory);
        }

        $this->newLine();
        $path = realpath($outputDirectory);
        // show the user the path where the images are stored
        $this->info("Images are stored in: {$path}");
    }

    protected function getRandomImages(array $schema, string $outputDirectory): void
    {
        ['amount' => $amount, 'size' => $size, 'terms' => $terms] = $schema;

        $this->comment(PHP_EOL . "Getting $amount random images of size $size, of topic: " . implode(', ', $terms));

        File::deleteDirectory($outputDirectory . $size);

        $progressBar = $this->output->createProgressBar($amount);
        $progressBar->start();

        foreach (range(1, $amount) as $i) {

            $url = "https://source.unsplash.com/{$size}/?img=1," . implode(',', $terms);
            $image = file_get_contents($url);

            File::ensureDirectoryExists($outputDirectory . '/' . $size);
            $filename = Str::uuid();

            File::put(
                path: "$outputDirectory/{$size}/{$filename}.jpg",
                contents: $image
            );

            $progressBar->advance();
        }

        $progressBar->finish();

        $this->newLine(2);
        $this->info('Done!');
    }
}

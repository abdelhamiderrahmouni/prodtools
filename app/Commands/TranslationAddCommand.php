<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class TranslationAddCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translation:add {key} {lang=en}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a new translation key to all language files with auto-translation';

    /**
     * Execute the console command.
     * @throws \JsonException
     */
    public function handle(): void
    {
        $key = $this->argument('key');
        $sourceLang = $this->argument('lang');

        $currentDir = getcwd();

        // check if the command is run in a laravel application
        if (!file_exists($currentDir . '/artisan')) {
            $this->error('This command must be run from the root of a Laravel application.');
            return;
        }

        // Get the value in the source language
        $sourceValue = $this->ask("Enter the value for '{$key}' in {$sourceLang}", $key);

        // Find all language files
        $langFiles = $this->getLangFiles($currentDir);

        $this->info("Found " . $langFiles->count() . " language files");

        // Process each language file
        foreach ($langFiles as $langFile) {
            $langCode = pathinfo($langFile, PATHINFO_FILENAME);
            $translations = json_decode(File::get($langFile), true, 512, JSON_THROW_ON_ERROR) ?? [];

            // Skip if the key already exists and user doesn't want to overwrite
            if (isset($translations[$key]) && !$this->confirm("Key '{$key}' already exists in {$langCode}. Overwrite?", false)) {
                $this->info("Skipped {$langCode}");
                continue;
            }

            // If it's the source language, use the provided value
            if ($langCode === $sourceLang) {
                $translations[$key] = $sourceValue;
            } else {
                // For other languages, try to translate
                $this->info("Translating to {$langCode}...");
                try {
                    $translatedValue = $this->translateText($sourceValue, $sourceLang, $langCode);
                    $translations[$key] = $translatedValue;
                    $this->info("Translated '{$key}' to {$langCode}: {$translatedValue}");
                } catch (\Exception $e) {
                    $this->error("Failed to translate to {$langCode}: " . $e->getMessage());
                    if ($this->confirm("Use source value for {$langCode}?", true)) {
                        $translations[$key] = $sourceValue;
                    } else {
                        continue;
                    }
                }
            }

            // Save the updated translations back to the file
            $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
            File::put($langFile, json_encode($translations, JSON_THROW_ON_ERROR | $jsonOptions));
        }

        $this->info("Translation for '{$key}' has been added to all language files");
    }

    /**
     * Translate text using an external service (e.g., Google Translate).
     *
     * @param $text
     * @param $sourceLang
     * @param $targetLang
     * @return string
     * @throws \Exception
     */
    protected function translateText(string $text, string $sourceLang, string $targetLang): string
    {
        // Using Google Translate API
        // Note: For production use, you should get an API key
        $response = Http::get('https://translate.googleapis.com/translate_a/single', [
            'client' => 'gtx',
            'sl' => $sourceLang,
            'tl' => $targetLang,
            'dt' => 't',
            'q' => $text,
        ]);

        if ($response->successful()) {
            $result = $response->json();
            if (isset($result[0][0][0])) {
                return $result[0][0][0];
            }
        }

        throw new \RuntimeException("Translation failed: " . $response->body());
    }

    /**
     * Get all language JSON files from both resource and base directories.
     *
     * @return \Illuminate\Support\Collection|int
     */
    private function getLangFiles(string $currentDir): \Illuminate\Support\Collection|int
    {
        // resource/lang directory
        $resourceLangPath = $currentDir . '/resources/lang';
        $langFiles = collect(File::glob("{$resourceLangPath}/*.json"));

        // base lang directory
        $baseLangPath = $currentDir . '/lang';
        $baseLangFiles = collect(File::glob("{$baseLangPath}/*.json"));

        // Merge both collections
        $langFiles = $langFiles->merge($baseLangFiles);

        if ($langFiles->isEmpty()) {
            $this->error('No language JSON files found!');
            return 1;
        }

        return $langFiles->filter(function ($file) {
            return File::isFile($file) && File::extension($file) === 'json';
        });
    }
}

<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GenerateLang extends Command
{
    protected string $from = '';
    protected array $targets = [];
    protected string|null $specificFile = null;
    protected string $onlyJson = '';
    protected string $langDir = '';
    protected string $sourcePath = '';

    protected $signature = 'translate {from} {to*} {--file=} {--json} {--lang-dir|dir=lang : The directory where the language files are stored}';
    protected $description = 'Translate language files from one language to another using Google Translate';

    public function handle()
    {
        $this->from = $this->argument('from');
        $this->targets = $this->argument('to');
        $this->specificFile = $this->option('file');
        $this->onlyJson = $this->option('json');
        $this->langDir = $this->option('lang-dir');
        $this->sourcePath = "{$this->langDir}/{$this->from}";

        if (!$this->onlyJson && !File::isDirectory($this->sourcePath)) {
            $this->error("The source language directory does not exist: {$this->sourcePath}");
            return;
        }

        if ($this->onlyJson) {
            $this->sourcePath = "{$this->langDir}/{$this->from}.json";
            if (!File::isFile($this->sourcePath)) {
                $this->error("The source language json file does not exist: {$this->sourcePath}");
                return;
            }
        }

        if ($this->onlyJson):
            $this->processJsonFile();
        else :
            $this->processDirectory();
        endif;

        $this->info("\n\n All files have been translate. \n");
    }

    protected function processJsonFile() :void
    {
        foreach ($this->targets as $to) {
            $this->info("\n\n 🔔 Translate to '{$to}'");

            // get content of json file
            $translations = json_decode(File::get($this->sourcePath), true, 512, JSON_THROW_ON_ERROR);

            $bar = $this->output->createProgressBar(count($translations));
            $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% -- %message%");
            $bar->setMessage('Initializing...');
            $bar->start();

            $bar->setMessage("🔄 Processing: {$this->sourcePath}");
            $bar->display();

            $translated = $this->translateArray($translations, $this->from, $to, $bar);

            $targetPath = "lang/{$to}.json";

            $outputContent = json_encode($translated, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            File::put($targetPath, $outputContent);

            $bar->setMessage("✅");
        }

        $bar->finish();
    }

    protected function processDirectory(): void
    {
        $filesToProcess = [];
        if ($this->specificFile) {
            $filePath = $this->sourcePath . '/' . $this->specificFile;
            if (!File::exists($filePath)) {
                $this->error("The specified file does not exist: {$filePath}");
                return;
            }
            $filesToProcess[] = ['path' => $filePath, 'relativePathname' => $this->specificFile];
        } else {
            foreach (File::allFiles($this->sourcePath) as $file) {
                $filesToProcess[] = ['path' => $file->getPathname(), 'relativePathname' => $file->getRelativePathname()];
            }
        }

        foreach ($this->targets as $to) {
            $this->info("\n\n 🔔 Translate to '{$to}'");

            $bar = $this->output->createProgressBar(count($filesToProcess));
            $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%% -- %message%");
            $bar->setMessage('Initializing...');
            $bar->start();

            foreach ($filesToProcess as $fileInfo) {
                $filePath = $fileInfo['relativePathname'];

                $bar->setMessage("🔄 Processing: {$filePath}");
                $bar->display();

                $translations = include $fileInfo['path'];
                $translated = $this->translateArray($translations, $this->from, $to);

                $targetPath = "lang/{$to}/" . dirname($filePath);
                if (!File::isDirectory($targetPath)) {
                    File::makeDirectory($targetPath, 0755, true, true);
                }

                $outputFile = "{$targetPath}/" . basename($filePath);
                $outputContent = "<?php\n\nreturn " . $this->arrayToString($translated) . ";\n";
                File::put($outputFile, $outputContent);

                $bar->advance();

                $bar->setMessage("✅");
            }

            $bar->finish();
        }
    }

    protected function translateArray($content, $source, $target, $bar = null)
    {
        if (is_array($content)) {
            foreach ($content as $key => $value) {
                $content[$key] = $this->translateArray($value, $source, $target);
                $bar?->advance();
            }
            return $content;
        } else if ($content === '' || $content === null){
            $this->error("Translation value missing, make sure all translation values are not empty, in source file!");
            exit();
        } else {
            return $this->translateUsingGoogleTranslate($content, $source, $target);
        }
    }

    public function translateUsingGoogleTranslate($content, string $source, string $target)
    {
        if (is_array($content)) {
            $translatedArray = [];
            foreach ($content as $key => $value) {
                $translatedArray[$key] = $this->translateUsingGoogleTranslate($value, $source, $target);
            }
            return $translatedArray;
        } else {
            $response = Http::retry(3)
                ->throw()
                ->get('https://translate.googleapis.com/translate_a/single?client=gtx&sl=' . $source . '&tl=' . $target . '&dt=t&q=' . urlencode($content));
            $response = json_decode($response->body());
            $translatedText = '';
            foreach ($response[0] as $translation) {
                $translatedText .= $translation[0];
            }
            return !empty($translatedText) ? $translatedText : $content;
        }
    }

    /**
     * Convert an array to a string representation using short array syntax.
     *
     * @param array $array The array to convert.
     * @param int $indentLevel The current indentation level (for formatting).
     * @return string The array as a string.
     */
    protected function arrayToString(array $array, $indentLevel = 1)
    {
        $indent = str_repeat('    ', $indentLevel); // 4 spaces for indentation
        $entries = [];

        foreach ($array as $key => $value) {
            $entryKey = is_string($key) ? "'$key'" : $key;
            if (is_array($value)) {
                $entryValue = $this->arrayToString($value, $indentLevel + 1);
                $entries[] = "$indent$entryKey => $entryValue";
            } else {
                // Escape single quotes inside strings
                $entryValue = is_string($value) ? "'" . addcslashes($value, "'") . "'" : $value;
                $entries[] = "$indent$entryKey => $entryValue";
            }
        }

        $glue = ",\n";
        $body = implode($glue, $entries);
        if ($indentLevel > 1) {
            return "[\n$body,\n" . str_repeat('    ', $indentLevel - 1) . ']';
        } else {
            return "[\n$body\n$indent]";
        }
    }
}

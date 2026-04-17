<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands\Make;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class ProofreadMakeDatasetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'proofread:make-dataset
        {name : The slug or class-like name of the dataset}
        {--path= : Destination directory (defaults to database/evals)}
        {--force : Overwrite the dataset file if it already exists}';

    /**
     * @var string
     */
    protected $description = 'Create a new Proofread dataset PHP file';

    public function handle(Filesystem $files): int
    {
        $rawName = $this->argument('name');
        if (! is_string($rawName) || trim($rawName) === '') {
            $this->components->error('Dataset name must not be empty.');

            return 1;
        }

        $slug = Str::slug($rawName);
        if ($slug === '') {
            $this->components->error(sprintf('Unable to derive a slug from "%s".', $rawName));

            return 1;
        }

        $pathOption = $this->option('path');
        $directory = is_string($pathOption) && $pathOption !== ''
            ? $pathOption
            : $this->laravel->basePath('database/evals');

        $fileName = Str::endsWith($slug, '-dataset') ? $slug.'.php' : $slug.'-dataset.php';
        $targetPath = rtrim($directory, '/').'/'.$fileName;

        if ($files->exists($targetPath) && ! (bool) $this->option('force')) {
            $this->components->error(sprintf('Dataset file already exists at [%s].', $targetPath));

            return 1;
        }

        if (! $files->isDirectory($directory)) {
            $files->makeDirectory($directory, 0755, true, true);
        }

        $stub = $files->get($this->stubPath());
        $contents = str_replace(['{{ name }}', '{{name}}'], $slug, $stub);

        $files->put($targetPath, $contents);

        $this->components->info(sprintf('Dataset [%s] created successfully.', $targetPath));

        return 0;
    }

    private function stubPath(): string
    {
        $published = $this->laravel->basePath('stubs/proofread/dataset.stub');
        if (is_file($published)) {
            return $published;
        }

        return __DIR__.'/../../../Stubs/dataset.stub';
    }
}

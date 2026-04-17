<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands\Make;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

final class ProofreadMakeAssertionCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'proofread:make-assertion';

    /**
     * @var string
     */
    protected $description = 'Create a new Proofread Assertion class';

    /**
     * @var string
     */
    protected $type = 'Assertion';

    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/proofread/assertion.stub');
        if (is_file($published)) {
            return $published;
        }

        return __DIR__.'/../../../Stubs/assertion.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Evals\\Assertions';
    }

    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);

        $class = str_replace($this->getNamespace($name).'\\', '', $name);

        $snakeClass = Str::snake($this->stripAssertionSuffix($class));

        return str_replace(['{{ snake_class }}', '{{snake_class}}'], $snakeClass, $stub);
    }

    private function stripAssertionSuffix(string $class): string
    {
        if (Str::endsWith($class, 'Assertion') && $class !== 'Assertion') {
            return Str::substr($class, 0, -9);
        }

        return $class;
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string}>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the assertion if it already exists'],
        ];
    }
}

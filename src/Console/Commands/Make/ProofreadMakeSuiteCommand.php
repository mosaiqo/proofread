<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands\Make;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

final class ProofreadMakeSuiteCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'proofread:make-suite';

    /**
     * @var string
     */
    protected $description = 'Create a new Proofread EvalSuite class';

    /**
     * @var string
     */
    protected $type = 'EvalSuite';

    protected function getStub(): string
    {
        $stubsPath = __DIR__.'/../../../Stubs';

        $published = $this->laravel->basePath('stubs/proofread');

        if ($this->option('multi')) {
            $custom = $published.'/eval-suite.multi.stub';
            if (is_file($custom)) {
                return $custom;
            }

            return $stubsPath.'/eval-suite.multi.stub';
        }

        $custom = $published.'/eval-suite.stub';
        if (is_file($custom)) {
            return $custom;
        }

        return $stubsPath.'/eval-suite.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Evals';
    }

    /**
     * @return array<int, array{0: string, 1: string|null, 2: int, 3: string}>
     */
    protected function getOptions(): array
    {
        return [
            ['multi', 'm', InputOption::VALUE_NONE, 'Generate a MultiSubjectEvalSuite instead of a single-subject suite'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite the suite if it already exists'],
        ];
    }
}

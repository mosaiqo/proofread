<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread;

use Mosaiqo\Proofread\Console\Commands\RunEvalsCommand;
use Mosaiqo\Proofread\Judge\Judge;
use Mosaiqo\Proofread\Similarity\Similarity;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ProofreadServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('proofread')
            ->hasConfigFile()
            ->hasCommand(RunEvalsCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(Judge::class, function ($app): Judge {
            /** @var array<string, mixed> $judgeConfig */
            $judgeConfig = $app['config']->get('proofread.judge', []);

            $defaultModel = $judgeConfig['default_model'] ?? 'claude-haiku-4-5';
            $maxRetries = $judgeConfig['max_retries'] ?? 1;

            return new Judge(
                defaultModel: is_string($defaultModel) ? $defaultModel : 'claude-haiku-4-5',
                maxRetries: is_int($maxRetries) ? $maxRetries : 1,
            );
        });

        $this->app->singleton(Similarity::class, function ($app): Similarity {
            /** @var array<string, mixed> $similarityConfig */
            $similarityConfig = $app['config']->get('proofread.similarity', []);

            $defaultModel = $similarityConfig['default_model'] ?? 'text-embedding-3-small';

            return new Similarity(
                defaultModel: is_string($defaultModel) && $defaultModel !== ''
                    ? $defaultModel
                    : 'text-embedding-3-small',
            );
        });
    }
}

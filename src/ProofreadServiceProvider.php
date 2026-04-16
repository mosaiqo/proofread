<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread;

use Mosaiqo\Proofread\Judge\Judge;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ProofreadServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('proofread')
            ->hasConfigFile();
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
    }
}

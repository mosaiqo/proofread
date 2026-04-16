<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread;

use Mosaiqo\Proofread\Console\Commands\RunEvalsCommand;
use Mosaiqo\Proofread\Judge\Judge;
use Mosaiqo\Proofread\Similarity\Similarity;
use Mosaiqo\Proofread\Snapshot\SnapshotStore;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ProofreadServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('proofread')
            ->hasConfigFile()
            ->hasMigration('create_eval_datasets_table')
            ->hasMigration('create_eval_runs_table')
            ->hasMigration('create_eval_results_table')
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

        $this->app->singleton(SnapshotStore::class, function ($app): SnapshotStore {
            /** @var array<string, mixed> $snapshotsConfig */
            $snapshotsConfig = $app['config']->get('proofread.snapshots', []);

            $path = $snapshotsConfig['path'] ?? sys_get_temp_dir().'/proofread-snapshots';
            $basePath = is_string($path) && $path !== ''
                ? $path
                : sys_get_temp_dir().'/proofread-snapshots';

            $envOverride = env('PROOFREAD_UPDATE_SNAPSHOTS');
            $configUpdate = $snapshotsConfig['update'] ?? false;
            $updateMode = (bool) ($envOverride ?? $configUpdate);

            return new SnapshotStore(
                basePath: $basePath,
                updateMode: $updateMode,
            );
        });
    }
}

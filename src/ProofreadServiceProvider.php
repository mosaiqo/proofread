<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Mosaiqo\Proofread\Console\Commands\RunEvalsCommand;
use Mosaiqo\Proofread\Console\Commands\ShadowEvaluateCommand;
use Mosaiqo\Proofread\Http\Middleware\ProofreadGate;
use Mosaiqo\Proofread\Judge\Judge;
use Mosaiqo\Proofread\Pricing\PricingTable;
use Mosaiqo\Proofread\Shadow\PiiSanitizer;
use Mosaiqo\Proofread\Shadow\ShadowAssertionsRegistry;
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
            ->hasMigration('create_shadow_captures_table')
            ->hasMigration('create_shadow_evals_table')
            ->hasCommand(RunEvalsCommand::class)
            ->hasCommand(ShadowEvaluateCommand::class)
            ->hasRoute('dashboard')
            ->hasViews('proofread');
    }

    public function packageBooted(): void
    {
        // Default gate: allow in local env only. Override this gate in your
        // AuthServiceProvider to control access in staging/production:
        //
        //     Gate::define('viewEvals', fn ($user) => $user?->isAdmin());
        if (! Gate::has('viewEvals')) {
            $app = $this->app;
            Gate::define('viewEvals', static fn ($user = null): bool => (bool) $app->environment('local'));
        }

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('proofread.gate', ProofreadGate::class);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(PricingTable::class, function ($app): PricingTable {
            /** @var array<string, array{input_per_1m: float, output_per_1m: float}> $models */
            $models = $app['config']->get('proofread.pricing.models', []);

            return PricingTable::fromArray($models);
        });

        $this->app->singleton(Judge::class, function ($app): Judge {
            /** @var array<string, mixed> $judgeConfig */
            $judgeConfig = $app['config']->get('proofread.judge', []);

            $defaultModel = $judgeConfig['default_model'] ?? 'claude-haiku-4-5';
            $maxRetries = $judgeConfig['max_retries'] ?? 1;

            return new Judge(
                defaultModel: is_string($defaultModel) ? $defaultModel : 'claude-haiku-4-5',
                maxRetries: is_int($maxRetries) ? $maxRetries : 1,
                pricing: $app->make(PricingTable::class),
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

        $this->app->singleton(ShadowAssertionsRegistry::class);

        $this->app->singleton(PiiSanitizer::class, function ($app): PiiSanitizer {
            /** @var array<string, mixed> $sanitizeConfig */
            $sanitizeConfig = $app['config']->get('proofread.shadow.sanitize', []);

            return PiiSanitizer::fromConfig($sanitizeConfig);
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

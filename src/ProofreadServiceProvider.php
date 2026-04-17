<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Pulse\Pulse;
use Laravel\Telescope\Telescope;
use Livewire\Livewire;
use Mosaiqo\Proofread\Clustering\FailureClusterer;
use Mosaiqo\Proofread\Console\Commands\BenchmarkEvalsCommand;
use Mosaiqo\Proofread\Console\Commands\ClusterFailuresCommand;
use Mosaiqo\Proofread\Console\Commands\CompareEvalsCommand;
use Mosaiqo\Proofread\Console\Commands\CoverageCommand;
use Mosaiqo\Proofread\Console\Commands\DatasetDiffCommand;
use Mosaiqo\Proofread\Console\Commands\ExportDatasetCommand;
use Mosaiqo\Proofread\Console\Commands\ExportRunCommand;
use Mosaiqo\Proofread\Console\Commands\GenerateDatasetCommand;
use Mosaiqo\Proofread\Console\Commands\ImportDatasetCommand;
use Mosaiqo\Proofread\Console\Commands\LintCommand;
use Mosaiqo\Proofread\Console\Commands\Make\ProofreadMakeAssertionCommand;
use Mosaiqo\Proofread\Console\Commands\Make\ProofreadMakeDatasetCommand;
use Mosaiqo\Proofread\Console\Commands\Make\ProofreadMakeSuiteCommand;
use Mosaiqo\Proofread\Console\Commands\RunEvalsCommand;
use Mosaiqo\Proofread\Console\Commands\RunProviderComparisonCommand;
use Mosaiqo\Proofread\Console\Commands\ShadowAlertCommand;
use Mosaiqo\Proofread\Console\Commands\ShadowEvaluateCommand;
use Mosaiqo\Proofread\Console\Commands\SimulateCostCommand;
use Mosaiqo\Proofread\Coverage\CoverageAnalyzer;
use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Events\EvalRunRegressed;
use Mosaiqo\Proofread\Generator\DatasetGenerator;
use Mosaiqo\Proofread\Http\Livewire\CompareRuns;
use Mosaiqo\Proofread\Http\Livewire\ComparisonDetail;
use Mosaiqo\Proofread\Http\Livewire\ComparisonsList;
use Mosaiqo\Proofread\Http\Livewire\CostsBreakdown;
use Mosaiqo\Proofread\Http\Livewire\DatasetsList;
use Mosaiqo\Proofread\Http\Livewire\Overview;
use Mosaiqo\Proofread\Http\Livewire\RunDetail;
use Mosaiqo\Proofread\Http\Livewire\RunsList;
use Mosaiqo\Proofread\Http\Livewire\ShadowPanel;
use Mosaiqo\Proofread\Http\Middleware\ProofreadGate;
use Mosaiqo\Proofread\Judge\Judge;
use Mosaiqo\Proofread\Lint\PromptLinter;
use Mosaiqo\Proofread\Lint\Rules\AmbiguityRule;
use Mosaiqo\Proofread\Lint\Rules\ContradictionRule;
use Mosaiqo\Proofread\Lint\Rules\LengthRule;
use Mosaiqo\Proofread\Lint\Rules\MissingOutputFormatRule;
use Mosaiqo\Proofread\Lint\Rules\MissingRoleRule;
use Mosaiqo\Proofread\Listeners\CheckForRegressionListener;
use Mosaiqo\Proofread\Listeners\NotifyWebhookOnRegression;
use Mosaiqo\Proofread\Mcp\McpIntegration;
use Mosaiqo\Proofread\Otel\EvalRunTracer;
use Mosaiqo\Proofread\Pricing\PricingTable;
use Mosaiqo\Proofread\Pulse\EvalPulseRecorder;
use Mosaiqo\Proofread\Runner\ComparisonPersister;
use Mosaiqo\Proofread\Runner\ComparisonRunner;
use Mosaiqo\Proofread\Runner\Concurrency\ConcurrencyDriver;
use Mosaiqo\Proofread\Runner\Concurrency\LaravelConcurrencyDriver;
use Mosaiqo\Proofread\Shadow\Contracts\RandomNumberProvider;
use Mosaiqo\Proofread\Shadow\MtRandRandomNumberProvider;
use Mosaiqo\Proofread\Shadow\PiiSanitizer;
use Mosaiqo\Proofread\Shadow\ShadowAlertService;
use Mosaiqo\Proofread\Shadow\ShadowAssertionsRegistry;
use Mosaiqo\Proofread\Similarity\Similarity;
use Mosaiqo\Proofread\Simulation\CostSimulator;
use Mosaiqo\Proofread\Snapshot\SnapshotStore;
use Mosaiqo\Proofread\Telescope\EvalRunWatcher;
use Mosaiqo\Proofread\Webhooks\RegressionWebhookNotifier;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ProofreadServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('proofread')
            ->hasConfigFile()
            ->discoversMigrations()
            ->hasCommand(RunEvalsCommand::class)
            ->hasCommand(BenchmarkEvalsCommand::class)
            ->hasCommand(RunProviderComparisonCommand::class)
            ->hasCommand(CompareEvalsCommand::class)
            ->hasCommand(ShadowEvaluateCommand::class)
            ->hasCommand(ShadowAlertCommand::class)
            ->hasCommand(GenerateDatasetCommand::class)
            ->hasCommand(ClusterFailuresCommand::class)
            ->hasCommand(SimulateCostCommand::class)
            ->hasCommand(CoverageCommand::class)
            ->hasCommand(DatasetDiffCommand::class)
            ->hasCommand(ExportRunCommand::class)
            ->hasCommand(ImportDatasetCommand::class)
            ->hasCommand(ExportDatasetCommand::class)
            ->hasCommand(LintCommand::class)
            ->hasCommand(ProofreadMakeSuiteCommand::class)
            ->hasCommand(ProofreadMakeAssertionCommand::class)
            ->hasCommand(ProofreadMakeDatasetCommand::class)
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

        McpIntegration::registerTools($this->app);

        Event::listen(EvalRunPersisted::class, CheckForRegressionListener::class);

        if (class_exists(Telescope::class)) {
            Event::listen(EvalRunPersisted::class, EvalRunWatcher::class);
        }

        if (class_exists(Pulse::class) && $this->app->bound(Pulse::class)) {
            Event::listen(EvalRunPersisted::class, EvalPulseRecorder::class);
        }

        if (interface_exists(TracerInterface::class)) {
            $this->app->singleton(EvalRunTracer::class, static function ($app): EvalRunTracer {
                $tracer = Globals::tracerProvider()
                    ->getTracer('mosaiqo/proofread', Proofread::VERSION);

                return new EvalRunTracer($tracer);
            });

            Event::listen(EvalRunPersisted::class, EvalRunTracer::class);
        }

        if ((bool) config('proofread.webhooks.enabled', false)) {
            Event::listen(EvalRunRegressed::class, NotifyWebhookOnRegression::class);
        }

        $this->registerLivewireComponents();

        $this->publishes([
            __DIR__.'/Stubs/eval-suite.stub' => $this->app->basePath('stubs/proofread/eval-suite.stub'),
            __DIR__.'/Stubs/eval-suite.multi.stub' => $this->app->basePath('stubs/proofread/eval-suite.multi.stub'),
            __DIR__.'/Stubs/assertion.stub' => $this->app->basePath('stubs/proofread/assertion.stub'),
            __DIR__.'/Stubs/dataset.stub' => $this->app->basePath('stubs/proofread/dataset.stub'),
        ], 'proofread-stubs');

        $this->publishes([
            __DIR__.'/../stubs/workflows/proofread.yml' => $this->app->basePath('.github/workflows/proofread.yml'),
        ], 'proofread-workflows');

        $this->publishes([
            __DIR__.'/../stubs/boost/proofread-guidelines.md' => $this->app->basePath('.ai/guidelines/proofread.md'),
        ], 'proofread-boost-guidelines');

        $this->publishes([
            __DIR__.'/../resources/views/pulse/proofread.blade.php' => $this->app->resourcePath('views/vendor/pulse/cards/proofread.blade.php'),
        ], 'proofread-pulse');
    }

    private function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('proofread::overview', Overview::class);
        Livewire::component('proofread::runs-list', RunsList::class);
        Livewire::component('proofread::run-detail', RunDetail::class);
        Livewire::component('proofread::datasets-list', DatasetsList::class);
        Livewire::component('proofread::compare-runs', CompareRuns::class);
        Livewire::component('proofread::comparisons-list', ComparisonsList::class);
        Livewire::component('proofread::comparison-detail', ComparisonDetail::class);
        Livewire::component('proofread::costs-breakdown', CostsBreakdown::class);
        Livewire::component('proofread::shadow-panel', ShadowPanel::class);
    }

    public function registeringPackage(): void
    {
        $this->app->bind(ConcurrencyDriver::class, LaravelConcurrencyDriver::class);

        $this->app->singleton(ComparisonRunner::class);
        $this->app->singleton(ComparisonPersister::class);

        $this->app->singleton(PricingTable::class, function ($app): PricingTable {
            /** @var array<string, array<string, mixed>> $models */
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

        $this->app->singleton(DatasetGenerator::class, function ($app): DatasetGenerator {
            /** @var array<string, mixed> $generatorConfig */
            $generatorConfig = $app['config']->get('proofread.generator', []);

            $defaultModel = $generatorConfig['default_model'] ?? 'claude-sonnet-4-6';
            $maxRetries = $generatorConfig['max_retries'] ?? 1;

            return new DatasetGenerator(
                defaultModel: is_string($defaultModel) && $defaultModel !== ''
                    ? $defaultModel
                    : 'claude-sonnet-4-6',
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

        $this->app->singleton(FailureClusterer::class, fn ($app): FailureClusterer => new FailureClusterer(
            $app->make(Similarity::class),
        ));

        $this->app->singleton(CostSimulator::class);

        $this->app->singleton(CoverageAnalyzer::class);

        $this->app->singleton(PromptLinter::class, fn ($app): PromptLinter => new PromptLinter([
            $app->make(LengthRule::class),
            $app->make(MissingRoleRule::class),
            $app->make(AmbiguityRule::class),
            $app->make(ContradictionRule::class),
            $app->make(MissingOutputFormatRule::class),
        ]));

        $this->app->bind(
            RandomNumberProvider::class,
            MtRandRandomNumberProvider::class,
        );

        $this->app->singleton(ShadowAssertionsRegistry::class);

        $this->app->singleton(ShadowAlertService::class, function ($app): ShadowAlertService {
            /** @var array<string, mixed> $alertsConfig */
            $alertsConfig = $app['config']->get('proofread.shadow.alerts', []);

            return ShadowAlertService::fromConfig(
                $app->make('cache.store'),
                $alertsConfig,
            );
        });

        $this->app->singleton(PiiSanitizer::class, function ($app): PiiSanitizer {
            /** @var array<string, mixed> $sanitizeConfig */
            $sanitizeConfig = $app['config']->get('proofread.shadow.sanitize', []);

            return PiiSanitizer::fromConfig($sanitizeConfig);
        });

        $this->app->singleton(RegressionWebhookNotifier::class, function ($app): RegressionWebhookNotifier {
            /** @var array<string, array{url: string, format: string}> $webhooks */
            $webhooks = $app['config']->get('proofread.webhooks.regressions', []);

            return new RegressionWebhookNotifier(
                $app->make(HttpFactory::class),
                $webhooks,
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

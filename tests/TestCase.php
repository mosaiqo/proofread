<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests;

use Illuminate\Contracts\Config\Repository;
use Laravel\Ai\AiServiceProvider;
use Laravel\Mcp\Server\McpServiceProvider;
use Livewire\LivewireServiceProvider;
use Mosaiqo\Proofread\ProofreadServiceProvider;
use Mosaiqo\Proofread\Runner\Concurrency\ConcurrencyDriver;
use Mosaiqo\Proofread\Runner\Concurrency\SyncConcurrencyDriver;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            LivewireServiceProvider::class,
            McpServiceProvider::class,
            ProofreadServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $app['env'] = 'local';
        $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app->bind(ConcurrencyDriver::class, SyncConcurrencyDriver::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

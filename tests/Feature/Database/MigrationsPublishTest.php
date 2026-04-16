<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

function proofreadPublishedMigrationsDir(): string
{
    return database_path('migrations');
}

function proofreadClearPublishedMigrations(): void
{
    $dir = proofreadPublishedMigrationsDir();

    if (File::isDirectory($dir)) {
        foreach (File::files($dir) as $file) {
            File::delete($file->getPathname());
        }

        return;
    }

    File::makeDirectory($dir, 0755, true);
}

beforeEach(function (): void {
    proofreadClearPublishedMigrations();
});

afterEach(function (): void {
    proofreadClearPublishedMigrations();
});

it('publishes all five migrations via the proofread-migrations tag', function (): void {
    Artisan::call('vendor:publish', [
        '--tag' => 'proofread-migrations',
        '--force' => true,
    ]);

    $publishedFilenames = collect(File::files(proofreadPublishedMigrationsDir()))
        ->map(fn ($file): string => $file->getFilename())
        ->values()
        ->all();

    $expectedSuffixes = [
        'create_eval_datasets_table.php',
        'create_eval_runs_table.php',
        'create_eval_results_table.php',
        'create_shadow_captures_table.php',
        'create_shadow_evals_table.php',
    ];

    foreach ($expectedSuffixes as $suffix) {
        $match = collect($publishedFilenames)->first(
            fn (string $name): bool => str_ends_with($name, $suffix)
        );

        expect($match)->not->toBeNull(
            "expected a published migration ending in {$suffix}, got: ".implode(', ', $publishedFilenames)
        );
    }
});

it('loads all five migration tables via migrate after publish', function (): void {
    Artisan::call('vendor:publish', [
        '--tag' => 'proofread-migrations',
        '--force' => true,
    ]);

    Artisan::call('migrate:fresh', [
        '--database' => 'testing',
        '--force' => true,
    ]);

    $expectedTables = [
        'eval_datasets',
        'eval_runs',
        'eval_results',
        'shadow_captures',
        'shadow_evals',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("expected table {$table} to exist after migrate:fresh");
    }
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Support\Dataset;

function proofread_cleanup_dataset_dir(): void
{
    $dir = base_path('database/evals');
    if (! is_dir($dir)) {
        return;
    }

    foreach (glob($dir.'/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    @rmdir($dir);
}

beforeEach(function (): void {
    proofread_cleanup_dataset_dir();
});

afterEach(function (): void {
    proofread_cleanup_dataset_dir();
});

it('generates a new Dataset file', function (): void {
    $exit = Artisan::call('proofread:make-dataset', ['name' => 'sentiment']);

    $expectedPath = base_path('database/evals/sentiment-dataset.php');

    expect($exit)->toBe(0)
        ->and(is_file($expectedPath))->toBeTrue();

    /** @var mixed $dataset */
    $dataset = require $expectedPath;

    expect($dataset)->toBeInstanceOf(Dataset::class)
        ->and($dataset->name)->toBe('sentiment');
});

it('creates the parent directory if missing', function (): void {
    $dir = base_path('database/evals');
    expect(is_dir($dir))->toBeFalse();

    Artisan::call('proofread:make-dataset', ['name' => 'onboarding']);

    expect(is_dir($dir))->toBeTrue();
});

it('includes three sample cases in the stub', function (): void {
    Artisan::call('proofread:make-dataset', ['name' => 'sample']);

    /** @var Dataset $dataset */
    $dataset = require base_path('database/evals/sample-dataset.php');

    expect($dataset->count())->toBe(3);
});

it('fails when the dataset file already exists', function (): void {
    Artisan::call('proofread:make-dataset', ['name' => 'first']);

    $exit = Artisan::call('proofread:make-dataset', ['name' => 'first']);

    expect($exit)->not->toBe(0);
});

it('overwrites with --force', function (): void {
    Artisan::call('proofread:make-dataset', ['name' => 'override']);

    $exit = Artisan::call('proofread:make-dataset', [
        'name' => 'override',
        '--force' => true,
    ]);

    expect($exit)->toBe(0);
});

it('respects the --path option', function (): void {
    $customDir = sys_get_temp_dir().'/proofread-dataset-'.bin2hex(random_bytes(4));

    try {
        $exit = Artisan::call('proofread:make-dataset', [
            'name' => 'custom',
            '--path' => $customDir,
        ]);

        $expectedPath = $customDir.'/custom-dataset.php';

        expect($exit)->toBe(0)
            ->and(is_file($expectedPath))->toBeTrue();
    } finally {
        if (is_dir($customDir)) {
            foreach (glob($customDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($customDir);
        }
    }
});

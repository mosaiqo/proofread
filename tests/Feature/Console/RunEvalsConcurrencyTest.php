<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;

it('accepts --concurrency flag with a valid integer', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--concurrency' => 3,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('2/2 passed');
});

it('accepts --concurrency=1 as a no-op default', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--concurrency' => 1,
    ]);

    expect($exit)->toBe(0);
});

it('treats --concurrency=0 as sequential (silent clamp)', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--concurrency' => 0,
    ]);

    expect($exit)->toBe(0);
});

it('exits 2 when --concurrency is not an integer', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--concurrency' => 'abc',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('--concurrency');
});

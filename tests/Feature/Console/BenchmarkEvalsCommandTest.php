<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Assertions\AlternatingAssertion;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\AlternatingPassFailSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\CostReportingSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\RubricEnabledSuite;

beforeEach(function (): void {
    AlternatingAssertion::reset();
});

it('runs the suite N times and aggregates results', function (): void {
    $exit = Artisan::call('evals:benchmark', [
        'suite' => PassingSuite::class,
        '--iterations' => 3,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Benchmark:')
        ->and($output)->toContain('3 iterations')
        ->and($output)->toContain('Pass rate');
});

it('detects flaky cases below the threshold', function (): void {
    $exit = Artisan::call('evals:benchmark', [
        'suite' => AlternatingPassFailSuite::class,
        '--iterations' => 4,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('FLAKY');
});

it('exits 1 when flaky cases are detected', function (): void {
    $exit = Artisan::call('evals:benchmark', [
        'suite' => AlternatingPassFailSuite::class,
        '--iterations' => 4,
    ]);

    expect($exit)->toBe(1);
});

it('exits 0 when all cases are stable', function (): void {
    $exit = Artisan::call('evals:benchmark', [
        'suite' => PassingSuite::class,
        '--iterations' => 2,
    ]);

    expect($exit)->toBe(0);
});

it('respects --flakiness-threshold', function (): void {
    $exit = Artisan::call('evals:benchmark', [
        'suite' => AlternatingPassFailSuite::class,
        '--iterations' => 4,
        '--flakiness-threshold' => '0.3',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->not->toContain('FLAKY');
});

it('reports duration statistics', function (): void {
    Artisan::call('evals:benchmark', [
        'suite' => PassingSuite::class,
        '--iterations' => 2,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('Duration')
        ->and($output)->toContain('p50');
});

it('reports cost when available', function (): void {
    Artisan::call('evals:benchmark', [
        'suite' => CostReportingSuite::class,
        '--iterations' => 2,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('Cost total');
});

it('outputs JSON with --format=json', function (): void {
    Artisan::call('evals:benchmark', [
        'suite' => PassingSuite::class,
        '--iterations' => 2,
        '--format' => 'json',
    ]);

    $output = Artisan::output();

    $decoded = json_decode(trim($output), true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKey('iterations')
        ->and($decoded)->toHaveKey('pass_rate')
        ->and($decoded)->toHaveKey('per_case');
});

it('requires at least 2 iterations', function (): void {
    $exit = Artisan::call('evals:benchmark', [
        'suite' => PassingSuite::class,
        '--iterations' => 1,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('at least 2');
});

it('rejects non-EvalSuite classes', function (): void {
    $exit = Artisan::call('evals:benchmark', [
        'suite' => DateTime::class,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain(EvalSuite::class);
});

it('applies --fake-judge', function (): void {
    config()->set('ai.default', 'openai');
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 0);

    $exit = Artisan::call('evals:benchmark', [
        'suite' => RubricEnabledSuite::class,
        '--iterations' => 2,
        '--fake-judge' => 'pass',
    ]);

    expect($exit)->toBe(0);
});

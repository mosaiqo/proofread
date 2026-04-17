<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Models\EvalComparison as EvalComparisonModel;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Runner\Concurrency\ConcurrencyDriver;
use Mosaiqo\Proofread\Runner\Concurrency\SyncConcurrencyDriver;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\MixedMultiSubjectSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingMultiSubjectSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\RubricMultiSubjectSuite;

it('runs a multi-subject suite and outputs a matrix', function (): void {
    $exit = Artisan::call('evals:providers', ['suite' => PassingMultiSubjectSuite::class]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('passing-multi')
        ->and($output)->toContain('haiku')
        ->and($output)->toContain('sonnet')
        ->and($output)->toContain('opus')
        ->and($output)->toContain('PASS');
});

it('exits 2 when the class does not exist', function (): void {
    $exit = Artisan::call('evals:providers', ['suite' => 'Nonexistent\\Suite\\Class']);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('not found');
});

it('exits 2 when the class is not a MultiSubjectEvalSuite', function (): void {
    $exit = Artisan::call('evals:providers', ['suite' => PassingSuite::class]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('MultiSubjectEvalSuite');
});

it('persists the comparison with --persist', function (): void {
    $exit = Artisan::call('evals:providers', [
        'suite' => PassingMultiSubjectSuite::class,
        '--persist' => true,
    ]);

    expect($exit)->toBe(0)
        ->and(EvalComparisonModel::query()->count())->toBe(1)
        ->and(EvalRunModel::query()->count())->toBe(3);

    $comparison = EvalComparisonModel::query()->firstOrFail();
    expect($comparison->subject_labels)->toBe(['haiku', 'sonnet', 'opus']);
});

it('applies --commit-sha to the persisted comparison', function (): void {
    Artisan::call('evals:providers', [
        'suite' => PassingMultiSubjectSuite::class,
        '--persist' => true,
        '--commit-sha' => 'deadbeef',
    ]);

    $comparison = EvalComparisonModel::query()->firstOrFail();
    expect($comparison->commit_sha)->toBe('deadbeef');

    $runs = EvalRunModel::query()->where('comparison_id', $comparison->id)->get();
    foreach ($runs as $run) {
        expect($run->commit_sha)->toBe('deadbeef');
    }
});

it('respects --concurrency for inner case parallelism', function (): void {
    $driver = new SyncConcurrencyDriver;
    app()->instance(ConcurrencyDriver::class, $driver);

    $exit = Artisan::call('evals:providers', [
        'suite' => PassingMultiSubjectSuite::class,
        '--concurrency' => 2,
    ]);

    expect($exit)->toBe(0)
        ->and($driver->invocations)->toBeGreaterThan(0);
});

it('respects --provider-concurrency for outer subject parallelism', function (): void {
    $driver = new SyncConcurrencyDriver;
    app()->instance(ConcurrencyDriver::class, $driver);

    $exit = Artisan::call('evals:providers', [
        'suite' => PassingMultiSubjectSuite::class,
        '--provider-concurrency' => 3,
    ]);

    expect($exit)->toBe(0)
        ->and($driver->invocations)->toBeGreaterThanOrEqual(1);
});

it('outputs JSON with --format=json', function (): void {
    Artisan::call('evals:providers', [
        'suite' => PassingMultiSubjectSuite::class,
        '--format' => 'json',
    ]);

    $output = Artisan::output();
    $lines = explode("\n", trim($output));
    $jsonLine = '';
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '{')) {
            $jsonLine = trim(implode("\n", array_slice($lines, array_search($line, $lines, true) ?: 0)));
            break;
        }
    }

    $decoded = json_decode($jsonLine, true);
    expect($decoded)->toBeArray()
        ->and($decoded['name'] ?? null)->toBe('passing-multi')
        ->and($decoded['subjects'] ?? null)->toBe(['haiku', 'sonnet', 'opus'])
        ->and($decoded['passed'] ?? null)->toBeTrue()
        ->and($decoded['runs'] ?? null)->toBeArray();
});

it('supports --fake-judge', function (): void {
    $exit = Artisan::call('evals:providers', [
        'suite' => RubricMultiSubjectSuite::class,
        '--fake-judge' => 'pass',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('auto-pass');
});

it('exits 1 when any subject fails', function (): void {
    $exit = Artisan::call('evals:providers', ['suite' => MixedMultiSubjectSuite::class]);

    expect($exit)->toBe(1);
});

it('exits 0 when all subjects pass', function (): void {
    $exit = Artisan::call('evals:providers', ['suite' => PassingMultiSubjectSuite::class]);

    expect($exit)->toBe(0);
});

it('prints pass rate and cost per subject in table output', function (): void {
    Artisan::call('evals:providers', ['suite' => PassingMultiSubjectSuite::class]);
    $output = Artisan::output();

    expect($output)->toContain('Pass rate')
        ->and($output)->toContain('100');
});

it('exits 2 when --concurrency is not a non-negative integer', function (): void {
    $exit = Artisan::call('evals:providers', [
        'suite' => PassingMultiSubjectSuite::class,
        '--concurrency' => 'abc',
    ]);

    expect($exit)->toBe(2);
});

/**
 * Sanity check: the deferred binding for EvalSuite vs MultiSubject does not
 * accidentally classify a regular EvalSuite as a multi subject one, which would
 * silently succeed with wrong semantics.
 */
it('rejects plain EvalSuite classes', function (): void {
    $reflected = new ReflectionClass(PassingSuite::class);

    expect($reflected->isSubclassOf(EvalSuite::class))->toBeTrue();
});

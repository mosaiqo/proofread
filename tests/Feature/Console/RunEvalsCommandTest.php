<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Mosaiqo\Proofread\Jobs\RunEvalSuiteJob;
use Mosaiqo\Proofread\Models\EvalDataset as EvalDatasetModel;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\EmptySuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\ErroringSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\FailingSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\FilterableSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;

function proofread_tmp_junit_path(string $suffix): string
{
    $dir = sys_get_temp_dir().'/proofread-'.bin2hex(random_bytes(4));
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir.'/'.$suffix;
}

afterEach(function (): void {
    $base = sys_get_temp_dir();
    foreach (glob($base.'/proofread-*') ?: [] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
});

it('runs a single suite that passes', function (): void {
    $exit = Artisan::call('evals:run', ['suites' => [PassingSuite::class]]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Running '.PassingSuite::class)
        ->and($output)->toContain('[PASS]')
        ->and($output)->toContain('2/2 passed');
});

it('runs a single suite that fails', function (): void {
    $exit = Artisan::call('evals:run', ['suites' => [FailingSuite::class]]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('[FAIL]')
        ->and($output)->toContain('Output does not contain "hello"');
});

it('runs a single suite with subject errors', function (): void {
    $exit = Artisan::call('evals:run', ['suites' => [ErroringSuite::class]]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('[ERR ]')
        ->and($output)->toContain('subject exploded');
});

it('runs multiple suites', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class, PassingSuite::class],
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and(substr_count($output, 'Running '.PassingSuite::class))->toBe(2);
});

it('exits 1 when any suite has failures', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class, FailingSuite::class],
    ]);

    expect($exit)->toBe(1);
});

it('exits 2 when a suite class does not exist', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => ['Nonexistent\\Suite\\Class'],
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain("Suite class 'Nonexistent\\Suite\\Class' not found");
});

it('exits 2 when a class does not extend EvalSuite', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [DateTime::class],
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain(DateTime::class)
        ->and($output)->toContain(EvalSuite::class);
});

it('stops at the first failure with --fail-fast', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class, FailingSuite::class, ErroringSuite::class],
        '--fail-fast' => true,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('Stopping due to --fail-fast')
        ->and($output)->not->toContain(ErroringSuite::class);
});

it('writes JUnit output when --junit is provided', function (): void {
    $path = proofread_tmp_junit_path('run.xml');

    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--junit' => $path,
    ]);

    expect($exit)->toBe(0)
        ->and(file_exists($path))->toBeTrue();

    $xml = file_get_contents($path);
    expect($xml)->toContain('<testsuites')
        ->and($xml)->toContain('name="passing"');
});

it('writes one JUnit file per suite when there are multiple', function (): void {
    $path = proofread_tmp_junit_path('run.xml');

    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class, FilterableSuite::class],
        '--junit' => $path,
    ]);

    $dir = dirname($path);
    $passingName = strtr(PassingSuite::class, '\\', '_');
    $filterableName = strtr(FilterableSuite::class, '\\', '_');

    expect($exit)->toBe(0)
        ->and(file_exists($dir.'/run.'.$passingName.'.xml'))->toBeTrue()
        ->and(file_exists($dir.'/run.'.$filterableName.'.xml'))->toBeTrue();
});

it('filters cases by name via --filter', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [FilterableSuite::class],
        '--filter' => 'foo',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('2/2 passed');
});

it('skips a suite whose filter matches no cases', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [FilterableSuite::class],
        '--filter' => 'no-match-substring-xyz',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No cases matching filter');
});

it('prints summary totals per suite', function (): void {
    Artisan::call('evals:run', [
        'suites' => [FailingSuite::class],
    ]);

    $output = Artisan::output();

    expect($output)->toContain('1/2 passed');
});

it('prints overall summary with exit indication', function (): void {
    Artisan::call('evals:run', [
        'suites' => [PassingSuite::class, FailingSuite::class],
    ]);

    $output = Artisan::output();

    expect($output)->toContain('Overall:')
        ->and($output)->toContain('exit 1');
});

it('handles a suite with an empty dataset', function (): void {
    $exit = Artisan::call('evals:run', ['suites' => [EmptySuite::class]]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No cases to run');
});

it('persists a run when --persist is provided', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--persist' => true,
    ]);

    expect($exit)->toBe(0)
        ->and(EvalDatasetModel::query()->count())->toBe(1)
        ->and(EvalRunModel::query()->count())->toBe(1);

    $run = EvalRunModel::query()->firstOrFail();
    expect($run->suite_class)->toBe(PassingSuite::class);
});

it('does not persist without --persist', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
    ]);

    expect($exit)->toBe(0)
        ->and(EvalDatasetModel::query()->count())->toBe(0)
        ->and(EvalRunModel::query()->count())->toBe(0);
});

it('prints the persisted run id when --persist is provided', function (): void {
    Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--persist' => true,
    ]);

    $output = Artisan::output();
    $run = EvalRunModel::query()->firstOrFail();

    expect($output)->toContain('Persisted as eval_run '.$run->id);
});

it('dispatches jobs when --queue is set', function (): void {
    Bus::fake();

    $exit = Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--queue' => true,
    ]);

    expect($exit)->toBe(0);
    Bus::assertDispatched(
        RunEvalSuiteJob::class,
        fn (RunEvalSuiteJob $job): bool => $job->suiteClass === PassingSuite::class,
    );
});

it('prints the queue name when dispatching', function (): void {
    Bus::fake();

    Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--queue' => true,
    ]);

    $output = Artisan::output();

    expect($output)->toContain('Queued '.PassingSuite::class)
        ->and($output)->toContain("queue 'evals'");
});

it('does not run inline when --queue is set', function (): void {
    Bus::fake();

    Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--queue' => true,
    ]);

    expect(EvalRunModel::query()->count())->toBe(0);
});

it('dispatches one job per suite when multiple suites are provided with --queue', function (): void {
    Bus::fake();

    Artisan::call('evals:run', [
        'suites' => [PassingSuite::class, FailingSuite::class],
        '--queue' => true,
    ]);

    Bus::assertDispatchedTimes(RunEvalSuiteJob::class, 2);
    Bus::assertDispatched(
        RunEvalSuiteJob::class,
        fn (RunEvalSuiteJob $job): bool => $job->suiteClass === PassingSuite::class,
    );
    Bus::assertDispatched(
        RunEvalSuiteJob::class,
        fn (RunEvalSuiteJob $job): bool => $job->suiteClass === FailingSuite::class,
    );
});

it('applies --commit-sha to the dispatched job', function (): void {
    Bus::fake();

    Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
        '--queue' => true,
        '--commit-sha' => 'deadbeef',
    ]);

    Bus::assertDispatched(
        RunEvalSuiteJob::class,
        fn (RunEvalSuiteJob $job): bool => $job->commitSha === 'deadbeef',
    );
});

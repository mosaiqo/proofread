<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Mosaiqo\Proofread\Jobs\RunEvalSuiteJob;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Models\EvalDataset as EvalDatasetModel;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\AssertionsForBugSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\AssertionsForSpySuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\EmptySuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\ErroringSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\FailingSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\FilterableSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\RubricEnabledSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\VaryingAssertionsSuite;

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

function proofread_configure_judge(): void
{
    config()->set('ai.default', 'openai');
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 0);
}

it('fakes the judge with pass when --fake-judge=pass is provided', function (): void {
    proofread_configure_judge();

    $exit = Artisan::call('evals:run', [
        'suites' => [RubricEnabledSuite::class],
        '--fake-judge' => 'pass',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('2/2 passed');
});

it('fakes the judge with fail when --fake-judge=fail is provided', function (): void {
    proofread_configure_judge();

    $exit = Artisan::call('evals:run', [
        'suites' => [RubricEnabledSuite::class],
        '--fake-judge' => 'fail',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('Auto-failed by --fake-judge');
});

it('loads judge responses from a JSON file when --fake-judge points to a path', function (): void {
    proofread_configure_judge();

    $dir = sys_get_temp_dir().'/proofread-'.bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    $path = $dir.'/responses.json';
    file_put_contents($path, json_encode([
        ['passed' => true, 'score' => 1.0, 'reason' => 'first-ok'],
        ['passed' => false, 'score' => 0.1, 'reason' => 'second-bad'],
    ]));

    $exit = Artisan::call('evals:run', [
        'suites' => [RubricEnabledSuite::class],
        '--fake-judge' => $path,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('second-bad')
        ->and($output)->toContain('1/2 passed');
});

it('exits 2 when --fake-judge SPEC is invalid', function (): void {
    proofread_configure_judge();

    $exit = Artisan::call('evals:run', [
        'suites' => [RubricEnabledSuite::class],
        '--fake-judge' => 'banana',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('--fake-judge');
});

it('exits 2 when --fake-judge file does not exist', function (): void {
    proofread_configure_judge();

    $exit = Artisan::call('evals:run', [
        'suites' => [RubricEnabledSuite::class],
        '--fake-judge' => '/nonexistent/path/to/missing-responses.json',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('--fake-judge');
});

it('exits 2 when --fake-judge file contains invalid JSON', function (): void {
    proofread_configure_judge();

    $dir = sys_get_temp_dir().'/proofread-'.bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    $path = $dir.'/bad.json';
    file_put_contents($path, 'not valid json {');

    $exit = Artisan::call('evals:run', [
        'suites' => [RubricEnabledSuite::class],
        '--fake-judge' => $path,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('--fake-judge');
});

it('prints a warning when --fake-judge is active', function (): void {
    proofread_configure_judge();

    Artisan::call('evals:run', [
        'suites' => [RubricEnabledSuite::class],
        '--fake-judge' => 'pass',
    ]);

    $output = Artisan::output();

    expect($output)->toContain('--fake-judge');
});

it('leaves the real judge wiring untouched when --fake-judge is not provided', function (): void {
    Artisan::call('evals:run', [
        'suites' => [PassingSuite::class],
    ]);

    expect(JudgeAgent::isFaked())->toBeFalse();
});

it('prints singular assertions count when all cases have the same count', function (): void {
    Artisan::call('evals:run', ['suites' => [PassingSuite::class]]);

    $output = Artisan::output();

    expect($output)->toContain('2 cases, 1 assertions per case')
        ->and($output)->not->toContain('1-1 assertions per case');
});

it('prints a base count with "per-case may vary" when assertionsFor is overridden', function (): void {
    Artisan::call('evals:run', ['suites' => [VaryingAssertionsSuite::class]]);

    $output = Artisan::output();

    expect($output)->toContain('2 cases, 2 base assertions (per-case may vary)');
});

it('invokes assertionsFor exactly once per case from the CLI', function (): void {
    AssertionsForSpySuite::reset();

    Artisan::call('evals:run', ['suites' => [AssertionsForSpySuite::class]]);

    expect(AssertionsForSpySuite::$callCount)->toBe(2);
});

it('invokes assertionsFor when running a suite via CLI', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [AssertionsForBugSuite::class],
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('[FAIL]')
        ->and($output)->toContain('count');
});

it('skips assertionsFor when the filter matches no cases via CLI', function (): void {
    AssertionsForSpySuite::reset();

    $exit = Artisan::call('evals:run', [
        'suites' => [AssertionsForSpySuite::class],
        '--filter' => 'no-match-substring-zzz',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('No cases matching filter')
        ->and(AssertionsForSpySuite::$callCount)->toBe(0);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
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

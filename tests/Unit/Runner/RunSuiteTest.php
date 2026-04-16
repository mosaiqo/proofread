<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Support\EvalRun;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\LifecycleSpySuite;

it('invokes setUp before reading dataset subject or assertions', function (): void {
    $runner = new EvalRunner;
    $suite = new LifecycleSpySuite;

    $runner->runSuite($suite);

    expect($suite->calls[0] ?? null)->toBe('setUp');
    expect($suite->calls)->toContain('dataset');
    expect($suite->calls)->toContain('subject');
    expect($suite->calls)->toContain('assertions');
});

it('invokes tearDown after the run completes', function (): void {
    $runner = new EvalRunner;
    $suite = new LifecycleSpySuite;

    $runner->runSuite($suite);

    expect($suite->calls)->toContain('tearDown');
    expect(end($suite->calls))->toBe('tearDown');
});

it('invokes tearDown even when the subject throws', function (): void {
    $runner = new EvalRunner;
    $suite = new LifecycleSpySuite;
    $suite->subjectThrows = new RuntimeException('boom');

    $runner->runSuite($suite);

    expect($suite->calls)->toContain('tearDown');
});

it('invokes tearDown even when an assertion throws', function (): void {
    $runner = new EvalRunner;
    $suite = new LifecycleSpySuite;
    $suite->assertionThrows = new RuntimeException('assertion-boom');

    $runner->runSuite($suite);

    expect($suite->calls)->toContain('tearDown');
});

it('returns the EvalRun from runSuite', function (): void {
    $runner = new EvalRunner;
    $suite = new LifecycleSpySuite;

    $run = $runner->runSuite($suite);

    expect($run)->toBeInstanceOf(EvalRun::class);
    expect($run->total())->toBe(1);
});

it('propagates setUp exceptions without calling tearDown', function (): void {
    $runner = new EvalRunner;
    $suite = new LifecycleSpySuite;
    $suite->setUpThrows = new RuntimeException('setup-failure');

    $caught = null;
    try {
        $runner->runSuite($suite);
    } catch (Throwable $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(RuntimeException::class);
    expect($caught?->getMessage())->toBe('setup-failure');
    expect($suite->calls)->not->toContain('tearDown');
    expect($suite->calls)->not->toContain('dataset');
});

it('surfaces a tearDown exception when the run succeeds', function (): void {
    $runner = new EvalRunner;
    $suite = new LifecycleSpySuite;
    $suite->tearDownThrows = new RuntimeException('teardown-failure');

    $caught = null;
    try {
        $runner->runSuite($suite);
    } catch (Throwable $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(RuntimeException::class);
    expect($caught?->getMessage())->toBe('teardown-failure');
});

<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
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

/**
 * @param  callable(mixed, array<string, mixed>): AssertionResult  $run
 */
function runSuiteAssertion(string $name, callable $run): Assertion
{
    return new class($name, $run) implements Assertion
    {
        /** @var callable(mixed, array<string, mixed>): AssertionResult */
        private $runner;

        /**
         * @param  callable(mixed, array<string, mixed>): AssertionResult  $runner
         */
        public function __construct(
            private readonly string $assertionName,
            callable $runner,
        ) {
            $this->runner = $runner;
        }

        public function run(mixed $output, array $context = []): AssertionResult
        {
            return ($this->runner)($output, $context);
        }

        public function name(): string
        {
            return $this->assertionName;
        }
    };
}

it('runSuite calls assertionsFor for each case', function (): void {
    $runner = new EvalRunner;
    $suite = new class extends EvalSuite
    {
        /** @var list<array<string, mixed>> */
        public array $received = [];

        public function dataset(): Dataset
        {
            return Dataset::make('multi', [
                ['input' => 'alpha'],
                ['input' => 'beta'],
                ['input' => 'gamma'],
            ]);
        }

        public function subject(): mixed
        {
            return static fn (string $input): string => $input;
        }

        public function assertions(): array
        {
            return [];
        }

        public function assertionsFor(array $case): array
        {
            $this->received[] = $case;

            return [];
        }
    };

    $runner->runSuite($suite);

    expect($suite->received)->toHaveCount(3);
    expect($suite->received[0]['input'])->toBe('alpha');
    expect($suite->received[1]['input'])->toBe('beta');
    expect($suite->received[2]['input'])->toBe('gamma');
});

it('runSuite respects per-case assertions', function (): void {
    $runner = new EvalRunner;
    $passing = runSuiteAssertion(
        'pass-only',
        fn (): AssertionResult => AssertionResult::pass('p'),
    );
    $failing = runSuiteAssertion(
        'fail-only',
        fn (): AssertionResult => AssertionResult::fail('f'),
    );

    $suite = new class($passing, $failing) extends EvalSuite
    {
        public function __construct(
            private readonly Assertion $passing,
            private readonly Assertion $failing,
        ) {}

        public function dataset(): Dataset
        {
            return Dataset::make('multi', [
                ['input' => 'a', 'meta' => ['kind' => 'pass']],
                ['input' => 'b', 'meta' => ['kind' => 'fail']],
            ]);
        }

        public function subject(): mixed
        {
            return static fn (string $input): string => $input;
        }

        public function assertions(): array
        {
            return [];
        }

        public function assertionsFor(array $case): array
        {
            $kind = $case['meta']['kind'] ?? null;

            return $kind === 'pass'
                ? [$this->passing]
                : [$this->failing];
        }
    };

    $run = $runner->runSuite($suite);

    expect($run->results[0]->passed())->toBeTrue();
    expect($run->results[0]->assertionResults[0]->metadata['assertion_name'] ?? null)
        ->toBe('pass-only');
    expect($run->results[1]->passed())->toBeFalse();
    expect($run->results[1]->assertionResults[0]->metadata['assertion_name'] ?? null)
        ->toBe('fail-only');
});

it('runSuite falls back to shared assertions when assertionsFor is not overridden', function (): void {
    $runner = new EvalRunner;
    $shared = runSuiteAssertion(
        'shared',
        fn (): AssertionResult => AssertionResult::pass('shared-ok'),
    );

    $suite = new class($shared) extends EvalSuite
    {
        public function __construct(private readonly Assertion $shared) {}

        public function dataset(): Dataset
        {
            return Dataset::make('multi', [
                ['input' => 'a'],
                ['input' => 'b'],
            ]);
        }

        public function subject(): mixed
        {
            return static fn (string $input): string => $input;
        }

        public function assertions(): array
        {
            return [$this->shared];
        }
    };

    $run = $runner->runSuite($suite);

    expect($run->results)->toHaveCount(2);
    foreach ($run->results as $result) {
        expect($result->assertionResults)->toHaveCount(1);
        expect($result->assertionResults[0]->metadata['assertion_name'] ?? null)
            ->toBe('shared');
    }
});

<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Runner\Concurrency\SyncConcurrencyDriver;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;

function makeEchoSuite(int $cases = 5): EvalSuite
{
    return new class($cases) extends EvalSuite
    {
        public function __construct(private readonly int $count) {}

        public function dataset(): Dataset
        {
            $cases = [];
            for ($i = 0; $i < $this->count; $i++) {
                $cases[] = ['input' => 'case-'.$i, 'meta' => ['name' => 'case-'.$i]];
            }

            return Dataset::make('concurrent', $cases);
        }

        public function subject(): mixed
        {
            return static fn (string $input): string => $input;
        }

        public function assertions(): array
        {
            return [ContainsAssertion::make('case-')];
        }
    };
}

it('produces identical results with concurrency 1 and N', function (): void {
    $sequential = new EvalRunner;
    $concurrent = new EvalRunner(concurrencyDriver: new SyncConcurrencyDriver);

    $runSequential = $sequential->runSuite(makeEchoSuite(5), concurrency: 1);
    $runConcurrent = $concurrent->runSuite(makeEchoSuite(5), concurrency: 3);

    expect($runConcurrent->total())->toBe($runSequential->total());
    expect($runConcurrent->passedCount())->toBe($runSequential->passedCount());

    foreach ($runConcurrent->results as $index => $result) {
        $other = $runSequential->results[$index];
        expect($result->output)->toBe($other->output);
        expect($result->passed())->toBe($other->passed());
        expect($result->case)->toBe($other->case);
        expect(count($result->assertionResults))->toBe(count($other->assertionResults));
    }
});

it('preserves case order regardless of concurrency', function (): void {
    $runner = new EvalRunner(concurrencyDriver: new SyncConcurrencyDriver);
    $suite = makeEchoSuite(10);

    $run = $runner->runSuite($suite, concurrency: 3);

    foreach ($run->results as $index => $result) {
        expect($result->output)->toBe('case-'.$index);
    }
});

it('clamps concurrency below 1 to sequential execution', function (): void {
    $driver = new SyncConcurrencyDriver;
    $runner = new EvalRunner(concurrencyDriver: $driver);

    $run = $runner->runSuite(makeEchoSuite(3), concurrency: 0);

    expect($run->total())->toBe(3);
    expect($driver->invocations)->toBe(0);
});

it('runs sequentially without invoking the concurrency driver when concurrency is 1', function (): void {
    $driver = new SyncConcurrencyDriver;
    $runner = new EvalRunner(concurrencyDriver: $driver);

    $runner->runSuite(makeEchoSuite(4), concurrency: 1);

    expect($driver->invocations)->toBe(0);
});

it('delegates to the concurrency driver when concurrency is greater than 1', function (): void {
    $driver = new SyncConcurrencyDriver;
    $runner = new EvalRunner(concurrencyDriver: $driver);

    $runner->runSuite(makeEchoSuite(6), concurrency: 3);

    expect($driver->invocations)->toBeGreaterThan(0);
    // 6 cases in chunks of 3 -> 2 invocations with 3 tasks each.
    expect($driver->invocations)->toBe(2);
    expect($driver->taskCountPerInvocation)->toBe([3, 3]);
});

it('measures overall wall-clock duration in concurrent mode', function (): void {
    $runner = new EvalRunner(concurrencyDriver: new SyncConcurrencyDriver);

    $run = $runner->runSuite(makeEchoSuite(4), concurrency: 2);

    expect($run->durationMs)->toBeGreaterThan(0.0);
});

it('propagates case errors in concurrent mode', function (): void {
    $runner = new EvalRunner(concurrencyDriver: new SyncConcurrencyDriver);

    $suite = new class extends EvalSuite
    {
        public function dataset(): Dataset
        {
            return Dataset::make('erroring', [
                ['input' => 'ok-1'],
                ['input' => 'boom'],
                ['input' => 'ok-3'],
            ]);
        }

        public function subject(): mixed
        {
            return static function (string $input): string {
                if ($input === 'boom') {
                    throw new RuntimeException('case-explosion');
                }

                return $input;
            };
        }

        public function assertions(): array
        {
            return [];
        }
    };

    $run = $runner->runSuite($suite, concurrency: 2);

    expect($run->results[0]->hasError())->toBeFalse();
    expect($run->results[1]->hasError())->toBeTrue();
    expect($run->results[1]->error?->getMessage())->toBe('case-explosion');
    expect($run->results[2]->hasError())->toBeFalse();
});

it('captures assertion results correctly in concurrent mode', function (): void {
    $runner = new EvalRunner(concurrencyDriver: new SyncConcurrencyDriver);

    $a = new class implements Assertion
    {
        public function run(mixed $output, array $context = []): AssertionResult
        {
            return AssertionResult::pass('a-ok');
        }

        public function name(): string
        {
            return 'alpha';
        }
    };
    $b = new class implements Assertion
    {
        public function run(mixed $output, array $context = []): AssertionResult
        {
            return AssertionResult::pass('b-ok');
        }

        public function name(): string
        {
            return 'beta';
        }
    };
    $c = new class implements Assertion
    {
        public function run(mixed $output, array $context = []): AssertionResult
        {
            return AssertionResult::pass('c-ok');
        }

        public function name(): string
        {
            return 'gamma';
        }
    };

    $suite = new class($a, $b, $c) extends EvalSuite
    {
        public function __construct(
            private readonly Assertion $a,
            private readonly Assertion $b,
            private readonly Assertion $c,
        ) {}

        public function dataset(): Dataset
        {
            return Dataset::make('multi', [
                ['input' => 'x'],
                ['input' => 'y'],
                ['input' => 'z'],
            ]);
        }

        public function subject(): mixed
        {
            return static fn (string $input): string => $input;
        }

        public function assertions(): array
        {
            return [$this->a, $this->b, $this->c];
        }
    };

    $run = $runner->runSuite($suite, concurrency: 3);

    foreach ($run->results as $result) {
        expect($result->assertionResults)->toHaveCount(3);
        $names = array_map(
            fn (AssertionResult $r): mixed => $r->metadata['assertion_name'] ?? null,
            $result->assertionResults,
        );
        expect($names)->toBe(['alpha', 'beta', 'gamma']);
    }
});

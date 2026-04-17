<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Runner\ComparisonRunner;
use Mosaiqo\Proofread\Runner\Concurrency\SyncConcurrencyDriver;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;

final class CMRSpySuite extends MultiSubjectEvalSuite
{
    /** @var list<string> */
    public array $calls = [];

    public int $setUpCount = 0;

    public int $tearDownCount = 0;

    public function name(): string
    {
        return 'cmr-spy';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('cmr-data', [
            ['input' => 'hello'],
            ['input' => 'world'],
        ]);
    }

    public function setUp(): void
    {
        $this->setUpCount++;
        $this->calls[] = 'setUp';
    }

    public function tearDown(): void
    {
        $this->tearDownCount++;
        $this->calls[] = 'tearDown';
    }

    public function assertions(): array
    {
        return [ContainsAssertion::make('h')];
    }

    public function subjects(): array
    {
        return [
            'alpha' => static fn (string $input): string => 'alpha:'.$input,
            'beta' => static fn (string $input): string => 'beta:'.$input,
            'gamma' => static fn (string $input): string => 'gamma:'.$input,
        ];
    }
}

final class CMRSubjectLabelSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'cmr-label';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('cmr-label-data', [
            ['input' => 'a'],
        ]);
    }

    public function assertions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array<int, Assertion>
     */
    public function assertionsFor(array $case): array
    {
        $label = $case['subject_label'] ?? 'unknown';

        return [new class($label) implements Assertion
        {
            public function __construct(private readonly mixed $expectedLabel) {}

            public function run(mixed $output, array $context = []): AssertionResult
            {
                if (($context['subject_label'] ?? null) === $this->expectedLabel) {
                    return AssertionResult::pass('label matches', null, ['assertion_name' => 'label']);
                }

                return AssertionResult::fail(
                    sprintf('expected label %s', (string) $this->expectedLabel),
                    null,
                    ['assertion_name' => 'label'],
                );
            }

            public function name(): string
            {
                return 'label';
            }
        }];
    }

    public function subjects(): array
    {
        return [
            'sub-a' => static fn (string $input): string => $input,
            'sub-b' => static fn (string $input): string => $input,
        ];
    }
}

final class CMRFailingTearDownSuite extends MultiSubjectEvalSuite
{
    public int $tearDownCount = 0;

    public function name(): string
    {
        return 'cmr-tear';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('cmr-tear-data', [['input' => 'x']]);
    }

    public function tearDown(): void
    {
        $this->tearDownCount++;
    }

    public function assertions(): array
    {
        return [];
    }

    public function subjects(): array
    {
        return [
            'good' => static fn (string $input): string => $input,
            'bad' => static function (string $input): string {
                throw new RuntimeException('provider exploded: '.$input);
            },
        ];
    }
}

final class CMREmptySubjectsSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'empty-cmr';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('empty', []);
    }

    public function assertions(): array
    {
        return [];
    }

    public function subjects(): array
    {
        return [];
    }
}

final class CMRBadLabelSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'bad-label-cmr';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('bad', [['input' => 'x']]);
    }

    public function assertions(): array
    {
        return [];
    }

    public function subjects(): array
    {
        return [
            '' => static fn (string $input): string => $input,
        ];
    }
}

it('runs the suite against every subject', function (): void {
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    $comparison = $runner->run(new CMRSpySuite, providerConcurrency: 1);

    expect(array_keys($comparison->runs))->toBe(['alpha', 'beta', 'gamma'])
        ->and($comparison->runs['alpha']->total())->toBe(2)
        ->and($comparison->runs['beta']->total())->toBe(2)
        ->and($comparison->runs['gamma']->total())->toBe(2);
});

it('runs setUp and tearDown once per comparison', function (): void {
    $suite = new CMRSpySuite;
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    $runner->run($suite, providerConcurrency: 1);

    expect($suite->setUpCount)->toBe(1)
        ->and($suite->tearDownCount)->toBe(1);
});

it('propagates dataset consistently to all runs', function (): void {
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    $comparison = $runner->run(new CMRSpySuite, providerConcurrency: 1);

    $alphaDataset = $comparison->runs['alpha']->dataset;
    expect($alphaDataset)->toBe($comparison->runs['beta']->dataset)
        ->and($alphaDataset)->toBe($comparison->runs['gamma']->dataset)
        ->and($alphaDataset->name)->toBe('cmr-data');
});

it('injects subject_label into case context for assertionsFor', function (): void {
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    $comparison = $runner->run(new CMRSubjectLabelSuite, providerConcurrency: 1);

    expect($comparison->runs['sub-a']->passed())->toBeTrue()
        ->and($comparison->runs['sub-b']->passed())->toBeTrue();
});

it('runs providers sequentially when providerConcurrency is 1', function (): void {
    $driver = new SyncConcurrencyDriver;
    $runner = new ComparisonRunner(new EvalRunner(concurrencyDriver: $driver), $driver);

    $runner->run(new CMRSpySuite, providerConcurrency: 1);

    expect($driver->invocations)->toBe(0);
});

it('runs providers in parallel when providerConcurrency > 1', function (): void {
    $driver = new SyncConcurrencyDriver;
    $runner = new ComparisonRunner(new EvalRunner(concurrencyDriver: new SyncConcurrencyDriver), $driver);

    $runner->run(new CMRSpySuite, providerConcurrency: 3);

    expect($driver->invocations)->toBeGreaterThanOrEqual(1)
        ->and(array_sum($driver->taskCountPerInvocation))->toBe(3);
});

it('applies caseConcurrency within each run', function (): void {
    $innerDriver = new SyncConcurrencyDriver;
    $outerDriver = new SyncConcurrencyDriver;
    $runner = new ComparisonRunner(new EvalRunner(concurrencyDriver: $innerDriver), $outerDriver);

    $runner->run(new CMRSpySuite, providerConcurrency: 1, caseConcurrency: 2);

    expect($innerDriver->invocations)->toBeGreaterThanOrEqual(3);
});

it('preserves subject ordering in the runs map', function (): void {
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    $comparison = $runner->run(new CMRSpySuite, providerConcurrency: 3);

    expect($comparison->subjectLabels())->toBe(['alpha', 'beta', 'gamma']);
});

it('measures wall-clock duration', function (): void {
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    $comparison = $runner->run(new CMRSpySuite, providerConcurrency: 1);

    expect($comparison->durationMs)->toBeGreaterThanOrEqual(0.0);
});

it('validates that subjects() is not empty', function (): void {
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    expect(fn () => $runner->run(new CMREmptySubjectsSuite))
        ->toThrow(InvalidArgumentException::class, 'subjects');
});

it('validates that subject labels are non-empty strings', function (): void {
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    expect(fn () => $runner->run(new CMRBadLabelSuite))
        ->toThrow(InvalidArgumentException::class);
});

it('still runs tearDown when a provider fails', function (): void {
    $suite = new CMRFailingTearDownSuite;
    $runner = new ComparisonRunner(new EvalRunner, new SyncConcurrencyDriver);

    $runner->run($suite, providerConcurrency: 1);

    expect($suite->tearDownCount)->toBe(1);
});

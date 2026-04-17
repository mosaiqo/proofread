<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalRun;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\ErroringSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\FailingSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\LifecycleSpySuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\ManyFailuresSuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    Proofread::registerPestExpectations();
});

it('passes when the suite runs cleanly', function (): void {
    expect(new PassingSuite)->toPassSuite();
});

it('fails when a case fails', function (): void {
    $caught = null;
    try {
        expect(new FailingSuite)->toPassSuite();
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(ExpectationFailedException::class);
    expect($caught?->getMessage() ?? '')->toContain('Output does not contain "hello"');
});

it('includes the suite name in the failure message', function (): void {
    $caught = null;
    try {
        expect(new FailingSuite)->toPassSuite();
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught?->getMessage() ?? '')->toContain(FailingSuite::class);
});

it('lists up to three failed cases', function (): void {
    $caught = null;
    try {
        expect(new ManyFailuresSuite)->toPassSuite();
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    $message = $caught?->getMessage() ?? '';
    expect($message)->toContain('case-0');
    expect($message)->toContain('case-1');
    expect($message)->toContain('case-2');
    expect($message)->not->toContain('case-3');
});

it('summarizes additional failures beyond three', function (): void {
    $suite = new class extends EvalSuite
    {
        public function dataset(): Dataset
        {
            $cases = [];
            for ($i = 0; $i < 5; $i++) {
                $cases[] = [
                    'input' => 'v-'.$i,
                    'meta' => ['name' => 'c-'.$i],
                ];
            }

            return Dataset::make('five-fails', $cases);
        }

        public function subject(): mixed
        {
            return static fn (string $input): string => $input;
        }

        public function assertions(): array
        {
            return [ContainsAssertion::make('unreachable')];
        }
    };

    $caught = null;
    try {
        expect($suite)->toPassSuite();
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught?->getMessage() ?? '')->toContain('and 2 more failures');
});

it('fails when the subject raises an error', function (): void {
    $caught = null;
    try {
        expect(new ErroringSuite)->toPassSuite();
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(ExpectationFailedException::class);
    expect($caught?->getMessage() ?? '')->toContain('subject exploded');
});

it('rejects non-EvalSuite values', function (): void {
    $caught = null;
    try {
        expect('string')->toPassSuite();
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(ExpectationFailedException::class);
    expect($caught?->getMessage() ?? '')->toContain('toPassSuite expects an EvalSuite');
});

it('invokes setUp and tearDown on the suite', function (): void {
    $suite = new LifecycleSpySuite;

    expect($suite)->toPassSuite();

    expect($suite->calls)->toContain('setUp');
    expect($suite->calls)->toContain('tearDown');
});

it('supports negation', function (): void {
    expect(new FailingSuite)->not->toPassSuite();
});

it('respects per-case assertionsFor', function (): void {
    $passing = new class implements Assertion
    {
        public function run(mixed $output, array $context = []): AssertionResult
        {
            return AssertionResult::pass('ok');
        }

        public function name(): string
        {
            return 'always-pass';
        }
    };

    $failing = new class implements Assertion
    {
        public function run(mixed $output, array $context = []): AssertionResult
        {
            return AssertionResult::fail('per-case failure');
        }

        public function name(): string
        {
            return 'per-case-fail';
        }
    };

    $suite = new class($passing, $failing) extends EvalSuite
    {
        public function __construct(
            private readonly Assertion $passing,
            private readonly Assertion $failing,
        ) {}

        public function dataset(): Dataset
        {
            return Dataset::make('per-case', [
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

            return $kind === 'pass' ? [$this->passing] : [$this->failing];
        }
    };

    $caught = null;
    try {
        expect($suite)->toPassSuite();
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    $message = $caught?->getMessage() ?? '';
    expect($message)->toContain('per-case-fail');
    expect($message)->toContain('per-case failure');
});

it('exposes the EvalRun via ->value after passing', function (): void {
    $run = expect(new PassingSuite)->toPassSuite()->value;

    expect($run)->toBeInstanceOf(EvalRun::class);
    expect($run->passed())->toBeTrue();
    expect($run->total())->toBeGreaterThan(0);
});

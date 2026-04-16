<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

use Closure;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;
use Mosaiqo\Proofread\Support\JudgeResult;
use Throwable;

final class EvalRunner
{
    private readonly SubjectResolver $resolver;

    public function __construct(?SubjectResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new SubjectResolver;
    }

    /**
     * Run the subject against every case in the dataset and evaluate assertions.
     *
     * When `$subject` is a callable, it is invoked as `fn (mixed $input, array $case): mixed`
     * where `$input` is `$case['input']` pre-unwrapped and `$case` is the full case array.
     * See {@see EvalSuite::subject()} for accepted subject shapes.
     *
     * @param  array<int, mixed>  $assertions
     */
    public function run(mixed $subject, Dataset $dataset, array $assertions): EvalRun
    {
        $resolved = $this->resolver->resolve($subject);
        $validated = $this->validateAssertions($assertions);

        $runStart = hrtime(true);
        $results = [];

        foreach ($dataset->cases as $index => $case) {
            $results[] = $this->runCase($resolved, $case, $index, $validated);
        }

        $durationMs = $this->roundMs((hrtime(true) - $runStart) / 1_000_000);

        return EvalRun::make($dataset, $results, $durationMs);
    }

    /**
     * Run an entire EvalSuite orchestrating its lifecycle.
     *
     * Invokes $suite->setUp(), resolves the subject once, then iterates
     * the dataset asking the suite for per-case assertions via
     * {@see EvalSuite::assertionsFor()}. tearDown runs in a finally
     * block so it triggers even if subject or assertions throw; it is
     * skipped when setUp itself throws, matching classic xUnit semantics.
     */
    public function runSuite(EvalSuite $suite): EvalRun
    {
        $suite->setUp();

        try {
            $dataset = $suite->dataset();
            $resolved = $this->resolver->resolve($suite->subject());

            $runStart = hrtime(true);
            $results = [];

            foreach ($dataset->cases as $index => $case) {
                $assertions = $this->validateAssertions($suite->assertionsFor($case));
                $results[] = $this->runCase($resolved, $case, $index, $assertions);
            }

            $durationMs = $this->roundMs((hrtime(true) - $runStart) / 1_000_000);

            return EvalRun::make($dataset, $results, $durationMs);
        } finally {
            $suite->tearDown();
        }
    }

    /**
     * @param  array<int, mixed>  $assertions
     * @return list<Assertion>
     */
    private function validateAssertions(array $assertions): array
    {
        $validated = [];
        foreach ($assertions as $index => $assertion) {
            if (! $assertion instanceof Assertion) {
                throw new InvalidArgumentException(
                    sprintf(
                        'assertions[%d] must implement %s, got %s.',
                        $index,
                        Assertion::class,
                        get_debug_type($assertion),
                    )
                );
            }
            $validated[] = $assertion;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  list<Assertion>  $assertions
     */
    private function runCase(Closure $subject, array $case, int $index, array $assertions): EvalResult
    {
        $caseStart = hrtime(true);
        $output = null;
        $error = null;
        $assertionResults = [];
        $invocation = null;
        $subjectLatencyMs = 0.0;

        $subjectStart = hrtime(true);
        try {
            $invocation = $subject($case['input'], $case);
            $output = $invocation->output;
        } catch (Throwable $e) {
            $error = $e;
        }
        $subjectLatencyMs = $this->roundMs((hrtime(true) - $subjectStart) / 1_000_000);

        if ($error === null && $invocation !== null) {
            $context = $case + [
                'case_index' => $index,
                'latency_ms' => $subjectLatencyMs,
            ] + $invocation->metadata;
            foreach ($assertions as $assertion) {
                $assertionResults[] = $this->runAssertion($assertion, $output, $context);
            }
        }

        $durationMs = $this->roundMs((hrtime(true) - $caseStart) / 1_000_000);

        return EvalResult::make($case, $output, $assertionResults, $durationMs, $error);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function runAssertion(Assertion $assertion, mixed $output, array $context): AssertionResult
    {
        try {
            $result = $assertion->run($output, $context);
        } catch (Throwable $e) {
            $result = AssertionResult::fail(
                sprintf('Assertion %s threw: %s', $assertion->name(), $e->getMessage())
            );
        }

        return $this->withAssertionName($result, $assertion->name());
    }

    private function withAssertionName(AssertionResult $result, string $name): AssertionResult
    {
        if (array_key_exists('assertion_name', $result->metadata)) {
            return $result;
        }

        $metadata = $result->metadata + ['assertion_name' => $name];

        if ($result instanceof JudgeResult) {
            $factory = $result->passed
                ? JudgeResult::pass(...)
                : JudgeResult::fail(...);

            return $factory(
                $result->reason,
                $result->score,
                $metadata,
                $result->judgeModel,
                $result->retryCount,
            );
        }

        return $result->passed
            ? AssertionResult::pass($result->reason, $result->score, $metadata)
            : AssertionResult::fail($result->reason, $result->score, $metadata);
    }

    private function roundMs(float $ms): float
    {
        return round($ms, 3);
    }
}

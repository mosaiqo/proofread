<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

use Closure;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Runner\Concurrency\ConcurrencyDriver;
use Mosaiqo\Proofread\Runner\Concurrency\LaravelConcurrencyDriver;
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

    private readonly ConcurrencyDriver $concurrencyDriver;

    public function __construct(
        ?SubjectResolver $resolver = null,
        ?ConcurrencyDriver $concurrencyDriver = null,
    ) {
        $this->resolver = $resolver ?? new SubjectResolver;
        $this->concurrencyDriver = $concurrencyDriver ?? new LaravelConcurrencyDriver;
    }

    /**
     * Run the subject against every case in the dataset and evaluate assertions.
     *
     * When `$subject` is a callable, it is invoked as `fn (mixed $input, array $case): mixed`
     * where `$input` is `$case['input']` pre-unwrapped and `$case` is the full case array.
     * See {@see EvalSuite::subject()} for accepted subject shapes.
     *
     * @param  array<int, mixed>  $assertions
     * @param  int  $concurrency  Maximum cases to execute in parallel. Default 1 (sequential).
     *                            See {@see self::runSuite()} for caveats.
     */
    public function run(mixed $subject, Dataset $dataset, array $assertions, int $concurrency = 1): EvalRun
    {
        $concurrency = max(1, $concurrency);
        $resolved = $this->resolver->resolve($subject);
        $validated = $this->validateAssertions($assertions);

        $runStart = hrtime(true);

        $results = $concurrency === 1
            ? $this->runFixedAssertionsSequentially($dataset, $resolved, $validated)
            : $this->runFixedAssertionsConcurrently($dataset, $resolved, $validated, $concurrency);

        $durationMs = $this->roundMs((hrtime(true) - $runStart) / 1_000_000);

        return EvalRun::make($dataset, $results, $durationMs);
    }

    /**
     * @param  list<Assertion>  $assertions
     * @return list<EvalResult>
     */
    private function runFixedAssertionsSequentially(Dataset $dataset, Closure $resolved, array $assertions): array
    {
        $results = [];
        foreach ($dataset->cases as $index => $case) {
            $results[] = $this->runCase($resolved, $case, $index, $assertions);
        }

        return $results;
    }

    /**
     * @param  list<Assertion>  $assertions
     * @param  int<1, max>  $concurrency
     * @return list<EvalResult>
     */
    private function runFixedAssertionsConcurrently(
        Dataset $dataset,
        Closure $resolved,
        array $assertions,
        int $concurrency,
    ): array {
        $indexed = [];
        foreach ($dataset->cases as $index => $case) {
            $indexed[] = ['index' => $index, 'case' => $case];
        }

        $chunks = array_chunk($indexed, $concurrency);

        $results = [];
        foreach ($chunks as $chunk) {
            $tasks = [];
            foreach ($chunk as $entry) {
                $case = $entry['case'];
                $index = $entry['index'];
                $tasks[] = fn (): EvalResult => $this->runCase($resolved, $case, $index, $assertions);
            }

            /** @var array<int, EvalResult> $chunkResults */
            $chunkResults = $this->concurrencyDriver->run($tasks);

            foreach ($chunkResults as $chunkResult) {
                $results[] = $chunkResult;
            }
        }

        return $results;
    }

    /**
     * Run an entire EvalSuite orchestrating its lifecycle.
     *
     * Invokes $suite->setUp(), resolves the subject once, then iterates
     * the dataset asking the suite for per-case assertions via
     * {@see EvalSuite::assertionsFor()}. tearDown runs in a finally
     * block so it triggers even if subject or assertions throw; it is
     * skipped when setUp itself throws, matching classic xUnit semantics.
     *
     * @param  int  $concurrency  Maximum cases to execute in parallel. Default 1 (sequential).
     *                            Values >1 batch cases into chunks and run each chunk concurrently
     *                            via Laravel's process-based Concurrency facade. Only beneficial
     *                            for I/O-bound subjects (LLM/HTTP calls). For deterministic
     *                            subjects, the forking/serialization overhead makes sequential
     *                            faster; keep at 1. Values <1 are clamped to 1.
     */
    public function runSuite(EvalSuite $suite, int $concurrency = 1): EvalRun
    {
        $concurrency = max(1, $concurrency);

        $suite->setUp();

        try {
            $dataset = $suite->dataset();
            $resolved = $this->resolver->resolve($suite->subject());

            $runStart = hrtime(true);

            $results = $concurrency === 1
                ? $this->runCasesSequentially($dataset, $resolved, $suite)
                : $this->runCasesConcurrently($dataset, $resolved, $suite, $concurrency);

            $durationMs = $this->roundMs((hrtime(true) - $runStart) / 1_000_000);

            return EvalRun::make($dataset, $results, $durationMs);
        } finally {
            $suite->tearDown();
        }
    }

    /**
     * Run a suite against an explicit subject tagged with a label.
     *
     * Used by {@see ComparisonRunner} to evaluate
     * the same dataset against multiple providers. The supplied subject
     * overrides whatever {@see EvalSuite::subject()} would return, and each
     * case context is enriched with `subject_label` so suites can branch on
     * it in {@see EvalSuite::assertionsFor()}.
     *
     * Does NOT invoke setUp/tearDown — the caller orchestrates those once
     * across the whole comparison.
     *
     * @internal
     */
    public function runSuiteForSubject(
        EvalSuite $suite,
        Dataset $dataset,
        string $subjectLabel,
        mixed $subject,
        int $concurrency = 1,
    ): EvalRun {
        $concurrency = max(1, $concurrency);
        $resolved = $this->resolver->resolve($subject);

        $runStart = hrtime(true);

        $results = $concurrency === 1
            ? $this->runCasesSequentiallyWithLabel($dataset, $resolved, $suite, $subjectLabel)
            : $this->runCasesConcurrentlyWithLabel($dataset, $resolved, $suite, $subjectLabel, $concurrency);

        $durationMs = $this->roundMs((hrtime(true) - $runStart) / 1_000_000);

        return EvalRun::make($dataset, $results, $durationMs);
    }

    /**
     * @return list<EvalResult>
     */
    private function runCasesSequentiallyWithLabel(
        Dataset $dataset,
        Closure $resolved,
        EvalSuite $suite,
        string $subjectLabel,
    ): array {
        $results = [];
        foreach ($dataset->cases as $index => $case) {
            $labeledCase = $case + ['subject_label' => $subjectLabel];
            $assertions = $this->validateAssertions($suite->assertionsFor($labeledCase));
            $results[] = $this->runCase($resolved, $labeledCase, $index, $assertions);
        }

        return $results;
    }

    /**
     * @param  int<1, max>  $concurrency
     * @return list<EvalResult>
     */
    private function runCasesConcurrentlyWithLabel(
        Dataset $dataset,
        Closure $resolved,
        EvalSuite $suite,
        string $subjectLabel,
        int $concurrency,
    ): array {
        $indexed = [];
        foreach ($dataset->cases as $index => $case) {
            $indexed[] = ['index' => $index, 'case' => $case + ['subject_label' => $subjectLabel]];
        }

        $chunks = array_chunk($indexed, $concurrency);

        $results = [];
        foreach ($chunks as $chunk) {
            $tasks = [];
            foreach ($chunk as $entry) {
                $case = $entry['case'];
                $index = $entry['index'];
                $assertions = $this->validateAssertions($suite->assertionsFor($case));
                $tasks[] = fn (): EvalResult => $this->runCase($resolved, $case, $index, $assertions);
            }

            /** @var array<int, EvalResult> $chunkResults */
            $chunkResults = $this->concurrencyDriver->run($tasks);

            foreach ($chunkResults as $chunkResult) {
                $results[] = $chunkResult;
            }
        }

        return $results;
    }

    /**
     * @return list<EvalResult>
     */
    private function runCasesSequentially(Dataset $dataset, Closure $resolved, EvalSuite $suite): array
    {
        $results = [];
        foreach ($dataset->cases as $index => $case) {
            $assertions = $this->validateAssertions($suite->assertionsFor($case));
            $results[] = $this->runCase($resolved, $case, $index, $assertions);
        }

        return $results;
    }

    /**
     * @param  int<1, max>  $concurrency
     * @return list<EvalResult>
     */
    private function runCasesConcurrently(
        Dataset $dataset,
        Closure $resolved,
        EvalSuite $suite,
        int $concurrency,
    ): array {
        $indexed = [];
        foreach ($dataset->cases as $index => $case) {
            $indexed[] = ['index' => $index, 'case' => $case];
        }

        $chunks = array_chunk($indexed, $concurrency);

        $results = [];
        foreach ($chunks as $chunk) {
            $tasks = [];
            foreach ($chunk as $entry) {
                $case = $entry['case'];
                $index = $entry['index'];
                $assertions = $this->validateAssertions($suite->assertionsFor($case));
                $tasks[] = fn (): EvalResult => $this->runCase($resolved, $case, $index, $assertions);
            }

            /** @var array<int, EvalResult> $chunkResults */
            $chunkResults = $this->concurrencyDriver->run($tasks);

            foreach ($chunkResults as $chunkResult) {
                $results[] = $chunkResult;
            }
        }

        return $results;
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

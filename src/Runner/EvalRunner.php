<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

use Closure;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;
use Throwable;

final class EvalRunner
{
    private readonly SubjectResolver $resolver;

    public function __construct(?SubjectResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new SubjectResolver;
    }

    /**
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
            return $assertion->run($output, $context);
        } catch (Throwable $e) {
            return AssertionResult::fail(
                sprintf('Assertion %s threw: %s', $assertion->name(), $e->getMessage())
            );
        }
    }

    private function roundMs(float $ms): float
    {
        return round($ms, 3);
    }
}

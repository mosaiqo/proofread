<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

use InvalidArgumentException;
use Mosaiqo\Proofread\Runner\Concurrency\ConcurrencyDriver;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalComparison;
use Mosaiqo\Proofread\Support\EvalRun;

final class ComparisonRunner
{
    public function __construct(
        private readonly EvalRunner $runner,
        private readonly ConcurrencyDriver $concurrency,
    ) {}

    /**
     * Run the suite against each declared subject and return the aggregate.
     *
     * @param  int  $providerConcurrency  Number of subjects to run in parallel.
     *                                    0 (default) runs all subjects in parallel.
     *                                    1 runs sequentially. Values >1 cap the pool size.
     * @param  int  $caseConcurrency  Per-run case parallelism (default 1).
     */
    public function run(
        MultiSubjectEvalSuite $suite,
        int $providerConcurrency = 0,
        int $caseConcurrency = 1,
    ): EvalComparison {
        $subjects = $suite->subjects();
        $this->validateSubjects($subjects);

        $caseConcurrency = max(1, $caseConcurrency);
        $suite->setUp();

        try {
            $dataset = $suite->dataset();

            $runStart = hrtime(true);

            $runs = $this->executeSubjects($suite, $dataset, $subjects, $providerConcurrency, $caseConcurrency);

            $durationMs = round((hrtime(true) - $runStart) / 1_000_000, 3);

            return EvalComparison::make($suite->name(), $dataset, $runs, $durationMs);
        } finally {
            $suite->tearDown();
        }
    }

    /**
     * @param  array<int|string, mixed>  $subjects
     */
    private function validateSubjects(array $subjects): void
    {
        if ($subjects === []) {
            throw new InvalidArgumentException('subjects() must return at least one subject.');
        }

        foreach ($subjects as $label => $subject) {
            if (! is_string($label) || $label === '') {
                throw new InvalidArgumentException(
                    sprintf('Subject labels must be non-empty strings, got %s.', get_debug_type($label))
                );
            }
            unset($subject);
        }
    }

    /**
     * @param  array<string, mixed>  $subjects
     * @return array<string, EvalRun>
     */
    private function executeSubjects(
        MultiSubjectEvalSuite $suite,
        Dataset $dataset,
        array $subjects,
        int $providerConcurrency,
        int $caseConcurrency,
    ): array {
        $count = count($subjects);
        $useParallel = $providerConcurrency !== 1 && $count > 1;

        if (! $useParallel) {
            $runs = [];
            foreach ($subjects as $label => $subject) {
                $runs[$label] = $this->runner->runSuiteForSubject(
                    $suite,
                    $dataset,
                    $label,
                    $subject,
                    $caseConcurrency,
                );
            }

            return $runs;
        }

        $poolSize = $providerConcurrency <= 0 ? $count : $providerConcurrency;
        $labels = array_keys($subjects);
        $entries = [];
        foreach ($labels as $label) {
            $entries[] = ['label' => $label, 'subject' => $subjects[$label]];
        }

        /** @var array<string, EvalRun> $runs */
        $runs = [];
        foreach (array_chunk($entries, $poolSize) as $chunk) {
            $tasks = [];
            foreach ($chunk as $entry) {
                $label = $entry['label'];
                $subject = $entry['subject'];
                $tasks[] = fn (): EvalRun => $this->runner->runSuiteForSubject(
                    $suite,
                    $dataset,
                    $label,
                    $subject,
                    $caseConcurrency,
                );
            }

            /** @var array<int, EvalRun> $chunkResults */
            $chunkResults = $this->concurrency->run($tasks);

            $chunkLabels = array_column($chunk, 'label');
            foreach ($chunkResults as $i => $result) {
                $label = $chunkLabels[$i];
                $runs[$label] = $result;
            }
        }

        $ordered = [];
        foreach ($labels as $label) {
            if (isset($runs[$label])) {
                $ordered[$label] = $runs[$label];
            }
        }

        return $ordered;
    }
}

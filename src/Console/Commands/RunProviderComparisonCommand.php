<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Mosaiqo\Proofread\Console\Support\JudgeFaker;
use Mosaiqo\Proofread\Runner\ComparisonPersister;
use Mosaiqo\Proofread\Runner\ComparisonRunner;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\EvalComparison;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

final class RunProviderComparisonCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:providers {suite : FQCN of a MultiSubjectEvalSuite}
        {--persist : Persist the comparison and its runs to the database}
        {--commit-sha= : Commit SHA attached to the persisted comparison}
        {--concurrency=1 : Cases per run to execute in parallel (inner)}
        {--provider-concurrency=0 : Subjects to run in parallel (outer). 0 = all}
        {--fake-judge= : Fake the judge agent for Rubric assertions: "pass", "fail", or a JSON file path}
        {--format=table : Output format: table | json}';

    /**
     * @var string
     */
    protected $description = 'Run a MultiSubjectEvalSuite against all declared subjects and report a comparison matrix.';

    public function handle(ComparisonRunner $runner, ComparisonPersister $persister): int
    {
        $suiteArgument = $this->argument('suite');
        $suiteName = is_string($suiteArgument) ? $suiteArgument : '';

        $caseConcurrency = $this->parseIntOption('concurrency', 1);
        if ($caseConcurrency === null) {
            return 2;
        }

        $providerConcurrency = $this->parseIntOption('provider-concurrency', 0);
        if ($providerConcurrency === null) {
            return 2;
        }

        $fakeJudgeOption = $this->option('fake-judge');
        $fakeJudgeSpec = is_string($fakeJudgeOption) && $fakeJudgeOption !== '' ? $fakeJudgeOption : null;
        if ($fakeJudgeSpec !== null && ! JudgeFaker::apply($this, $fakeJudgeSpec)) {
            return 2;
        }

        $formatOption = $this->option('format');
        $format = is_string($formatOption) && $formatOption !== '' ? $formatOption : 'table';
        if (! in_array($format, ['table', 'json'], true)) {
            $this->error(sprintf("--format must be 'table' or 'json', got '%s'", $format));

            return 2;
        }

        $commitShaOption = $this->option('commit-sha');
        $commitSha = is_string($commitShaOption) && $commitShaOption !== '' ? $commitShaOption : null;
        $persist = (bool) $this->option('persist');

        $suite = $this->resolveSuite($suiteName);
        if ($suite === null) {
            return 2;
        }

        $comparison = $runner->run(
            $suite,
            providerConcurrency: $providerConcurrency,
            caseConcurrency: $caseConcurrency,
        );

        if ($format === 'json') {
            $this->line(json_encode($this->buildJsonPayload($comparison), JSON_PRETTY_PRINT) ?: '{}');
        } else {
            $this->printMatrix($comparison);
        }

        if ($persist) {
            $model = $persister->persist($comparison, suiteClass: $suite::class, commitSha: $commitSha);
            $this->line('');
            $this->line('  Persisted as eval_comparison '.$model->id);
        }

        return $comparison->passed() ? 0 : 1;
    }

    private function resolveSuite(string $name): ?MultiSubjectEvalSuite
    {
        if (! class_exists($name)) {
            $this->error(sprintf("Suite class '%s' not found", $name));

            return null;
        }

        if (! is_subclass_of($name, MultiSubjectEvalSuite::class)) {
            $this->error(sprintf(
                "Class '%s' must extend %s",
                $name,
                MultiSubjectEvalSuite::class,
            ));

            return null;
        }

        /** @var MultiSubjectEvalSuite $instance */
        $instance = app($name);

        return $instance;
    }

    private function parseIntOption(string $option, int $default): ?int
    {
        $raw = $this->option($option);
        if ($raw === null) {
            return $default;
        }

        if (is_int($raw)) {
            return max(0, $raw);
        }

        if (! is_string($raw) || $raw === '') {
            return $default;
        }

        if (preg_match('/^\d+$/', $raw) !== 1) {
            $this->error(sprintf(
                "--%s must be a non-negative integer, got '%s'",
                $option,
                $raw,
            ));

            return null;
        }

        return max(0, (int) $raw);
    }

    private function printMatrix(EvalComparison $comparison): void
    {
        $dataset = $comparison->dataset;
        $labels = $comparison->subjectLabels();
        $caseCount = $dataset->count();

        $this->line(sprintf(
            'Comparison "%s" — %d cases × %d subjects',
            $comparison->name,
            $caseCount,
            count($labels),
        ));
        $this->line('');

        $caseColumn = 'case';
        $caseColumnWidth = max(strlen($caseColumn), 10);
        foreach ($dataset->cases as $index => $case) {
            $caseColumnWidth = max($caseColumnWidth, strlen($this->caseLabel($case, $index)));
        }
        $caseColumnWidth = max($caseColumnWidth, strlen('Pass rate'));
        $caseColumnWidth = max($caseColumnWidth, strlen('Cost'));
        $caseColumnWidth = max($caseColumnWidth, strlen('Avg latency'));

        $cellWidth = 10;
        foreach ($labels as $label) {
            $cellWidth = max($cellWidth, strlen($label));
        }

        $header = str_pad($caseColumn, $caseColumnWidth);
        foreach ($labels as $label) {
            $header .= ' | '.str_pad($label, $cellWidth);
        }
        $this->line('  '.$header);

        $sep = str_repeat('-', $caseColumnWidth);
        foreach ($labels as $_) {
            $sep .= ' | '.str_repeat('-', $cellWidth);
        }
        $this->line('  '.$sep);

        foreach ($dataset->cases as $index => $case) {
            $row = str_pad($this->caseLabel($case, $index), $caseColumnWidth);
            foreach ($labels as $label) {
                $run = $comparison->runForSubject($label);
                $result = $run?->results[$index] ?? null;
                $row .= ' | '.str_pad($this->statusFor($result), $cellWidth);
            }
            $this->line('  '.$row);
        }

        $this->line('  '.$sep);

        $passRateRow = str_pad('Pass rate', $caseColumnWidth);
        foreach ($labels as $label) {
            $run = $comparison->runForSubject($label);
            $rate = $run?->passRate() ?? 0.0;
            $passRateRow .= ' | '.str_pad($this->formatPercent($rate), $cellWidth);
        }
        $this->line('  '.$passRateRow);

        $costRow = str_pad('Cost', $caseColumnWidth);
        $totals = $comparison->totalCosts();
        foreach ($labels as $label) {
            $costRow .= ' | '.str_pad($this->formatCost($totals[$label] ?? null), $cellWidth);
        }
        $this->line('  '.$costRow);

        $latencyRow = str_pad('Avg latency', $caseColumnWidth);
        foreach ($labels as $label) {
            $run = $comparison->runForSubject($label);
            $latencyRow .= ' | '.str_pad($this->formatLatency($run), $cellWidth);
        }
        $this->line('  '.$latencyRow);

        $this->line('');
        $totalCases = $caseCount * count($labels);
        $passedCases = 0;
        foreach ($comparison->runs as $run) {
            $passedCases += $run->passedCount();
        }
        $failedCases = $totalCases - $passedCases;

        $this->line(sprintf(
            'Overall: %d/%d passed, %d failed, %s total',
            $passedCases,
            $totalCases,
            $failedCases,
            $this->formatDuration($comparison->durationMs),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJsonPayload(EvalComparison $comparison): array
    {
        $runs = [];
        $passRates = $comparison->passRates();
        $costs = $comparison->totalCosts();

        foreach ($comparison->subjectLabels() as $label) {
            $run = $comparison->runForSubject($label);
            if ($run === null) {
                continue;
            }

            $runs[] = [
                'subject_label' => $label,
                'passed' => $run->passed(),
                'pass_rate' => round($passRates[$label] ?? 0.0, 3),
                'cost_usd' => $costs[$label] ?? null,
                'avg_latency_ms' => $this->averageLatency($run),
                'duration_ms' => $run->durationMs,
                'total_cases' => $run->total(),
                'passed_cases' => $run->passedCount(),
                'failed_cases' => $run->failedCount(),
            ];
        }

        return [
            'name' => $comparison->name,
            'dataset' => $comparison->dataset->name,
            'subjects' => $comparison->subjectLabels(),
            'total_cases' => $comparison->dataset->count(),
            'duration_ms' => $comparison->durationMs,
            'passed' => $comparison->passed(),
            'runs' => $runs,
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function caseLabel(array $case, int $index): string
    {
        $meta = $case['meta'] ?? null;
        if (is_array($meta)) {
            $name = $meta['name'] ?? null;
            if (is_string($name) && $name !== '') {
                return sprintf('case %d (%s)', $index, $name);
            }
        }

        return 'case '.$index;
    }

    private function statusFor(?EvalResult $result): string
    {
        if ($result === null) {
            return '-';
        }

        if ($result->hasError()) {
            return 'ERR';
        }

        return $result->passed() ? 'PASS' : 'FAIL';
    }

    private function formatPercent(float $rate): string
    {
        return number_format($rate * 100.0, 1).'%';
    }

    private function formatCost(?float $cost): string
    {
        if ($cost === null) {
            return '-';
        }

        return '$'.number_format($cost, 4);
    }

    private function formatLatency(?EvalRun $run): string
    {
        if ($run === null) {
            return '-';
        }

        $avg = $this->averageLatency($run);
        if ($avg === null) {
            return '-';
        }

        return ((int) round($avg)).'ms';
    }

    private function averageLatency(EvalRun $run): ?float
    {
        $values = [];
        foreach ($run->results as $result) {
            foreach ($result->assertionResults as $assertion) {
                $latency = $assertion->metadata['latency_ms'] ?? null;
                if (is_int($latency) || is_float($latency)) {
                    $values[] = (float) $latency;
                    break;
                }
            }
        }

        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    private function formatDuration(float $ms): string
    {
        if ($ms >= 1000.0) {
            return number_format($ms / 1000.0, 2).'s';
        }

        return ((int) round($ms)).'ms';
    }
}

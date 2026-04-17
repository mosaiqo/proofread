<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Mosaiqo\Proofread\Console\Support\JudgeFaker;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\EvalRun;
use Throwable;

/**
 * Run a suite multiple times and report stability statistics.
 *
 * Unlike `evals:run`, this command treats the suite as a fixed benchmark
 * and measures how reliably it passes across N iterations. Useful when the
 * underlying subject is non-deterministic (LLMs) and you want to quantify
 * flakiness and pricing variance before trusting a pass/fail signal from
 * a single run.
 *
 * Exit codes:
 * - 0 → every case is stable (pass ratio >= threshold across iterations).
 * - 1 → one or more cases fall below the flakiness threshold.
 * - 2 → argument/resolution error.
 */
final class BenchmarkEvalsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:benchmark
        {suite : FQCN of the EvalSuite subclass to benchmark}
        {--iterations=10 : Number of iterations (>= 2)}
        {--concurrency=1 : Cases per iteration run in parallel}
        {--fake-judge= : Fake the judge agent: "pass", "fail", or a JSON path}
        {--flakiness-threshold=0.8 : Minimum per-case pass ratio to consider stable}
        {--format=table : Output format: table or json}';

    /**
     * @var string
     */
    protected $description = 'Run a suite N times and report pass-rate variance, duration percentiles, cost, and flakiness.';

    public function handle(EvalRunner $runner): int
    {
        $suiteArg = $this->argument('suite');
        $suiteName = is_string($suiteArg) ? $suiteArg : '';

        $suite = $this->resolveSuite($suiteName);
        if ($suite === null) {
            return 2;
        }

        $iterations = $this->parseIterations($this->option('iterations'));
        if ($iterations === null) {
            return 2;
        }

        $concurrency = $this->parseConcurrency($this->option('concurrency'));
        if ($concurrency === null) {
            return 2;
        }

        $threshold = $this->parseThreshold($this->option('flakiness-threshold'));
        if ($threshold === null) {
            return 2;
        }

        $format = $this->parseFormat($this->option('format'));
        if ($format === null) {
            return 2;
        }

        $fakeJudgeOption = $this->option('fake-judge');
        $fakeJudgeSpec = is_string($fakeJudgeOption) && $fakeJudgeOption !== '' ? $fakeJudgeOption : null;
        if ($fakeJudgeSpec !== null && ! JudgeFaker::apply($this, $fakeJudgeSpec)) {
            return 2;
        }

        /** @var list<EvalRun> $runs */
        $runs = [];
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $runs[] = $runner->runSuite($suite, concurrency: $concurrency);
            } catch (Throwable $e) {
                $this->error(sprintf('Iteration %d failed: %s', $i + 1, $e->getMessage()));

                return 1;
            }
        }

        $stats = $this->aggregate($runs);
        $flaky = $this->detectFlaky($stats['per_case'], $threshold);

        if ($format === 'json') {
            $this->line((string) json_encode(
                [
                    'suite' => $suite::class,
                    'iterations' => $iterations,
                    'pass_rate' => $stats['pass_rate'],
                    'duration_ms' => $stats['duration_ms'],
                    'cost_usd' => $stats['cost_usd'],
                    'per_case' => $stats['per_case'],
                    'flakiness_threshold' => $threshold,
                    'flaky_cases' => $flaky,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } else {
            $this->renderTable($suite::class, $iterations, $threshold, $stats, $flaky);
        }

        return $flaky === [] ? 0 : 1;
    }

    private function resolveSuite(string $name): ?EvalSuite
    {
        if ($name === '' || ! class_exists($name)) {
            $this->error(sprintf("Suite class '%s' not found", $name));

            return null;
        }

        if (! is_subclass_of($name, EvalSuite::class)) {
            $this->error(sprintf(
                "Class '%s' does not extend %s",
                $name,
                EvalSuite::class,
            ));

            return null;
        }

        /** @var EvalSuite $instance */
        $instance = app($name);

        return $instance;
    }

    private function parseIterations(mixed $raw): ?int
    {
        if (! is_numeric($raw)) {
            $this->error('--iterations must be an integer >= 2');

            return null;
        }

        $value = (int) $raw;
        if ($value < 2) {
            $this->error('--iterations must be at least 2 for benchmark statistics');

            return null;
        }

        return $value;
    }

    private function parseConcurrency(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return 1;
        }

        if (! is_numeric($raw)) {
            $this->error('--concurrency must be a non-negative integer');

            return null;
        }

        return max(1, (int) $raw);
    }

    private function parseThreshold(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return 0.8;
        }

        if (! is_numeric($raw)) {
            $this->error('--flakiness-threshold must be a number between 0.0 and 1.0');

            return null;
        }

        $value = (float) $raw;
        if ($value < 0.0 || $value > 1.0) {
            $this->error(sprintf('--flakiness-threshold must be between 0.0 and 1.0, got %s', (string) $raw));

            return null;
        }

        return $value;
    }

    private function parseFormat(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return 'table';
        }

        $format = is_string($raw) ? strtolower($raw) : 'table';
        if ($format !== 'table' && $format !== 'json') {
            $this->error(sprintf('--format must be "table" or "json", got "%s"', $format));

            return null;
        }

        return $format;
    }

    /**
     * @param  list<EvalRun>  $runs
     * @return array{
     *     pass_rate: array{mean: float, min: float, max: float, stddev: float, values: list<float>},
     *     duration_ms: array{mean: float, p50: float, p95: float, values: list<float>},
     *     cost_usd: array{total: float|null, per_run_avg: float|null, values: list<float|null>},
     *     per_case: list<array{case_index: int, name: string, passes: int, iterations: int, stability: float}>,
     * }
     */
    private function aggregate(array $runs): array
    {
        $passRateValues = [];
        $durationValues = [];
        $costValues = [];
        /** @var array<int, array{name: string, passes: int}> $caseStats */
        $caseStats = [];

        foreach ($runs as $run) {
            $passRateValues[] = $run->passRate();
            $durationValues[] = $run->durationMs;
            $costValues[] = $this->runCost($run);

            foreach ($run->results as $index => $result) {
                if (! isset($caseStats[$index])) {
                    $caseStats[$index] = [
                        'name' => $this->caseName($result->case, $index),
                        'passes' => 0,
                    ];
                }
                if ($result->passed()) {
                    $caseStats[$index]['passes']++;
                }
            }
        }

        $iterations = count($runs);

        $perCase = [];
        ksort($caseStats);
        foreach ($caseStats as $index => $entry) {
            $perCase[] = [
                'case_index' => $index,
                'name' => $entry['name'],
                'passes' => $entry['passes'],
                'iterations' => $iterations,
                'stability' => $iterations === 0 ? 1.0 : $entry['passes'] / $iterations,
            ];
        }

        $costNumeric = array_values(array_filter(
            $costValues,
            static fn (?float $value): bool => $value !== null,
        ));
        $totalCost = $costNumeric === [] ? null : array_sum($costNumeric);
        $perRunAvg = $costNumeric === [] ? null : ($totalCost ?? 0.0) / max(1, count($costNumeric));

        $passMin = $passRateValues === [] ? 0.0 : min($passRateValues);
        $passMax = $passRateValues === [] ? 0.0 : max($passRateValues);

        return [
            'pass_rate' => [
                'mean' => $this->mean($passRateValues),
                'min' => $passMin,
                'max' => $passMax,
                'stddev' => $this->stddev($passRateValues),
                'values' => $passRateValues,
            ],
            'duration_ms' => [
                'mean' => $this->mean($durationValues),
                'p50' => $this->percentile($durationValues, 0.50),
                'p95' => $this->percentile($durationValues, 0.95),
                'values' => $durationValues,
            ],
            'cost_usd' => [
                'total' => $totalCost,
                'per_run_avg' => $perRunAvg,
                'values' => $costValues,
            ],
            'per_case' => $perCase,
        ];
    }

    private function runCost(EvalRun $run): ?float
    {
        $total = null;
        foreach ($run->results as $result) {
            foreach ($result->assertionResults as $assertion) {
                if (! array_key_exists('cost_usd', $assertion->metadata)) {
                    continue;
                }
                $value = $assertion->metadata['cost_usd'];
                if (is_int($value) || is_float($value)) {
                    $total = ($total ?? 0.0) + (float) $value;
                }
            }
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function caseName(array $case, int $index): string
    {
        $meta = $case['meta'] ?? null;
        if (is_array($meta)) {
            $name = $meta['name'] ?? null;
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return 'case_'.$index;
    }

    /**
     * @param  list<array{case_index: int, name: string, passes: int, iterations: int, stability: float}>  $perCase
     * @return list<array{case_index: int, name: string, stability: float}>
     */
    private function detectFlaky(array $perCase, float $threshold): array
    {
        $flaky = [];
        foreach ($perCase as $entry) {
            if ($entry['stability'] < $threshold) {
                $flaky[] = [
                    'case_index' => $entry['case_index'],
                    'name' => $entry['name'],
                    'stability' => $entry['stability'],
                ];
            }
        }

        return $flaky;
    }

    /**
     * @param  list<float>  $values
     */
    private function mean(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param  list<float>  $values
     */
    private function stddev(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $mean = $this->mean($values);
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }

        return sqrt($sumSq / count($values));
    }

    /**
     * @param  list<float>  $values
     */
    private function percentile(array $values, float $p): float
    {
        if ($values === []) {
            return 0.0;
        }

        $sorted = $values;
        sort($sorted);
        $index = (int) floor($p * (count($sorted) - 1));

        return $sorted[$index];
    }

    /**
     * @param  array{
     *     pass_rate: array{mean: float, min: float, max: float, stddev: float, values: list<float>},
     *     duration_ms: array{mean: float, p50: float, p95: float, values: list<float>},
     *     cost_usd: array{total: float|null, per_run_avg: float|null, values: list<float|null>},
     *     per_case: list<array{case_index: int, name: string, passes: int, iterations: int, stability: float}>,
     * }  $stats
     * @param  list<array{case_index: int, name: string, stability: float}>  $flaky
     */
    private function renderTable(
        string $suiteClass,
        int $iterations,
        float $threshold,
        array $stats,
        array $flaky,
    ): void {
        $this->line(sprintf('Benchmark: %s over %d iterations', $suiteClass, $iterations));
        $this->line('');

        $passRate = $stats['pass_rate'];
        $duration = $stats['duration_ms'];
        $cost = $stats['cost_usd'];

        $this->line('Overall:');
        $this->line(sprintf(
            '  Pass rate:  %s ± %s (min %s, max %s)',
            $this->formatRate($passRate['mean']),
            $this->formatRate($passRate['stddev']),
            $this->formatRate($passRate['min']),
            $this->formatRate($passRate['max']),
        ));
        $this->line(sprintf(
            '  Duration:   %s avg (p50 %s, p95 %s)',
            $this->formatMs($duration['mean']),
            $this->formatMs($duration['p50']),
            $this->formatMs($duration['p95']),
        ));

        if ($cost['total'] !== null) {
            $this->line(sprintf(
                '  Cost total: %s (%s per run avg)',
                $this->formatUsd($cost['total']),
                $cost['per_run_avg'] !== null ? $this->formatUsd($cost['per_run_avg']) : 'n/a',
            ));
        } else {
            $this->line('  Cost total: n/a');
        }

        $this->line('');
        $this->line('Per-case stability:');
        foreach ($stats['per_case'] as $entry) {
            $stabilityLabel = number_format($entry['stability'] * 100, 0, '.', '').'%';
            $statusLabel = $entry['stability'] < $threshold ? 'FLAKY' : 'stable';
            $this->line(sprintf(
                '  case_%d (%s)  %s  %s',
                $entry['case_index'],
                $entry['name'],
                $stabilityLabel,
                $statusLabel,
            ));
        }

        $this->line('');
        $this->line(sprintf('Flaky cases: %d', count($flaky)));
    }

    private function formatRate(float $rate): string
    {
        return number_format($rate * 100, 1, '.', '').'%';
    }

    private function formatMs(float $ms): string
    {
        return number_format($ms, 1, '.', '').' ms';
    }

    private function formatUsd(float $value): string
    {
        return '$'.number_format($value, 4, '.', '');
    }
}

<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Mosaiqo\Proofread\Console\Support\JudgeFaker;
use Mosaiqo\Proofread\Jobs\RunEvalSuiteJob;
use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Runner\EvalPersister;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;
use Throwable;

final class RunEvalsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'evals:run {suites* : FQCNs of EvalSuite subclasses to run}
        {--junit= : Write JUnit XML to this path (one file per suite when multiple are given)}
        {--fail-fast : Stop at the first suite that fails or errors}
        {--filter= : Case-insensitive substring filter against case meta.name or stringified input}
        {--persist : Persist each run to the database via EvalPersister}
        {--queue : Dispatch each suite to the queue instead of running inline}
        {--commit-sha= : Commit SHA attached to the persisted run (only used with --queue)}
        {--concurrency=1 : Run up to N cases in parallel. Default 1 (sequential). Only beneficial for I/O-bound subjects.}
        {--fake-judge= : Fake the judge agent for Rubric assertions: "pass", "fail", or a JSON file path}
        {--gate-pass-rate= : Fail the command (exit 1) if the overall pass rate is below this ratio (0.0 - 1.0)}
        {--gate-cost-max= : Fail the command (exit 1) if the total observed cost in USD exceeds this value}';

    /**
     * @var string
     */
    protected $description = 'Run one or more Proofread eval suites and report the results.';

    public function handle(EvalRunner $runner, EvalPersister $persister): int
    {
        /** @var array<int, string> $suiteNames */
        $suiteNames = (array) $this->argument('suites');
        $junitOption = $this->option('junit');
        $junitPath = is_string($junitOption) && $junitOption !== '' ? $junitOption : null;
        $filterOption = $this->option('filter');
        $filter = is_string($filterOption) && $filterOption !== '' ? $filterOption : null;
        $failFast = (bool) $this->option('fail-fast');
        $persist = (bool) $this->option('persist');
        $queue = (bool) $this->option('queue');
        $commitShaOption = $this->option('commit-sha');
        $commitSha = is_string($commitShaOption) && $commitShaOption !== '' ? $commitShaOption : null;
        $fakeJudgeOption = $this->option('fake-judge');
        $fakeJudgeSpec = is_string($fakeJudgeOption) && $fakeJudgeOption !== '' ? $fakeJudgeOption : null;
        $multipleSuites = count($suiteNames) > 1;

        $concurrency = $this->parseConcurrency($this->option('concurrency'));
        if ($concurrency === null) {
            return 2;
        }

        $gatePassRate = $this->parseGatePassRate($this->option('gate-pass-rate'));
        if ($gatePassRate === false) {
            return 2;
        }

        $gateCostMax = $this->parseGateCostMax($this->option('gate-cost-max'));
        if ($gateCostMax === false) {
            return 2;
        }

        if ($fakeJudgeSpec !== null && ! JudgeFaker::apply($this, $fakeJudgeSpec)) {
            return 2;
        }

        $suites = [];
        foreach ($suiteNames as $name) {
            $suite = $this->resolveSuite($name);
            if ($suite === null) {
                return 2;
            }
            $suites[] = $suite;
        }

        if ($queue) {
            return $this->dispatchSuites($suites, $commitSha);
        }

        $anyFailure = false;
        $executed = 0;
        $totalPassed = 0;
        $totalCases = 0;
        $totalCost = null;

        $filterClosure = $this->buildFilterClosure($filter);

        foreach ($suites as $suite) {
            $this->line('Running '.$suite->name());

            try {
                $run = $runner->runSuite($suite, concurrency: $concurrency, filter: $filterClosure);
            } catch (Throwable $e) {
                $this->error('  Suite runner failed: '.$e->getMessage());
                $anyFailure = true;
                $executed++;

                if ($failFast) {
                    $this->line('Stopping due to --fail-fast');
                    break;
                }

                continue;
            }

            if ($run->total() === 0) {
                $this->line($filter !== null ? '  No cases matching filter' : '  No cases to run');
                $executed++;

                continue;
            }

            $this->line($this->assertionsHeader($suite, $run->dataset));
            $this->line('');

            $this->printRun($run);

            $totalPassed += $run->passedCount();
            $totalCases += $run->total();
            $runCost = $this->computeRunCost($run);
            if ($runCost !== null) {
                $totalCost = ($totalCost ?? 0.0) + $runCost;
            }

            if ($junitPath !== null) {
                $targetPath = $this->junitPathFor($junitPath, $suite, $multipleSuites);
                Proofread::writeJUnit($run, $targetPath);
                $this->line('');
                $this->line('  JUnit written to: '.$targetPath);
            }

            if ($persist) {
                $model = $persister->persist($run, suiteClass: $suite::class);
                $this->line('');
                $this->line('  Persisted as eval_run '.$model->id);
            }

            $this->line('');

            $executed++;

            if ($run->failed()) {
                $anyFailure = true;
                if ($failFast) {
                    $this->line('Stopping due to --fail-fast');
                    break;
                }
            }
        }

        $gateFailed = $this->renderGates($gatePassRate, $gateCostMax, $totalPassed, $totalCases, $totalCost);

        $exit = ($anyFailure || $gateFailed) ? 1 : 0;
        $failedSuites = $anyFailure ? 1 : 0;
        $this->line(sprintf(
            'Overall: %d suite(s) executed, %s, exit %d',
            $executed,
            $failedSuites === 0 ? 'all passed' : '1 suite failed',
            $exit,
        ));

        return $exit;
    }

    private function parseGatePassRate(mixed $raw): float|false|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw) && ! is_numeric($raw)) {
            $this->error('--gate-pass-rate must be a number between 0.0 and 1.0');

            return false;
        }

        if (is_string($raw) && ! is_numeric($raw)) {
            $this->error(sprintf("--gate-pass-rate must be a number between 0.0 and 1.0, got '%s'", $raw));

            return false;
        }

        $value = (float) $raw;
        if ($value < 0.0 || $value > 1.0) {
            $this->error(sprintf('--gate-pass-rate must be between 0.0 and 1.0, got %s', (string) $raw));

            return false;
        }

        return $value;
    }

    private function parseGateCostMax(mixed $raw): float|false|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw) && ! is_numeric($raw)) {
            $this->error('--gate-cost-max must be a non-negative number');

            return false;
        }

        if (is_string($raw) && ! is_numeric($raw)) {
            $this->error(sprintf("--gate-cost-max must be a non-negative number, got '%s'", $raw));

            return false;
        }

        $value = (float) $raw;
        if ($value < 0.0) {
            $this->error(sprintf('--gate-cost-max must be >= 0, got %s', (string) $raw));

            return false;
        }

        return $value;
    }

    private function computeRunCost(EvalRun $run): ?float
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

    private function renderGates(
        ?float $gatePassRate,
        ?float $gateCostMax,
        int $totalPassed,
        int $totalCases,
        ?float $totalCost,
    ): bool {
        if ($gatePassRate === null && $gateCostMax === null) {
            return false;
        }

        $this->line('Gates:');
        $anyFail = false;

        if ($gatePassRate !== null) {
            $observed = $totalCases === 0 ? 1.0 : $totalPassed / $totalCases;
            $passed = $observed >= $gatePassRate;
            $status = $passed ? 'OK' : 'FAIL';
            $this->line(sprintf(
                '  Pass rate gate: %s required, %s observed %s %s',
                $this->formatRate($gatePassRate),
                $this->formatRate($observed),
                $passed ? '—' : '<',
                $status,
            ));
            if (! $passed) {
                $anyFail = true;
            }
        }

        if ($gateCostMax !== null) {
            $observedCost = $totalCost ?? 0.0;
            $passed = $observedCost <= $gateCostMax;
            $status = $passed ? 'OK' : 'FAIL';
            $this->line(sprintf(
                '  Cost gate: %s max, %s total — %s',
                $this->formatUsd($gateCostMax),
                $this->formatUsd($observedCost),
                $status,
            ));
            if (! $passed) {
                $anyFail = true;
            }
        }

        $this->line('');

        return $anyFail;
    }

    private function formatRate(float $rate): string
    {
        return number_format($rate * 100, 1, '.', '').'%';
    }

    private function formatUsd(float $value): string
    {
        return '$'.number_format($value, 4, '.', '');
    }

    /**
     * @param  array<int, EvalSuite>  $suites
     */
    private function dispatchSuites(array $suites, ?string $commitSha): int
    {
        $queueConfig = config('proofread.queue.eval_queue', 'evals');
        $queueName = is_string($queueConfig) && $queueConfig !== '' ? $queueConfig : 'evals';

        foreach ($suites as $suite) {
            RunEvalSuiteJob::dispatch($suite::class, $commitSha, true);
            $this->line(sprintf(
                "Queued %s for async execution on queue '%s'",
                $suite::class,
                $queueName,
            ));
        }

        return 0;
    }

    private function parseConcurrency(mixed $raw): ?int
    {
        if ($raw === null) {
            return 1;
        }

        if (is_int($raw)) {
            return max(1, $raw);
        }

        if (! is_string($raw) || $raw === '') {
            return 1;
        }

        if (preg_match('/^-?\d+$/', $raw) !== 1) {
            $this->error(sprintf(
                "--concurrency must be a non-negative integer, got '%s'",
                $raw,
            ));

            return null;
        }

        return max(1, (int) $raw);
    }

    private function resolveSuite(string $name): ?EvalSuite
    {
        if (! class_exists($name)) {
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

    /**
     * @return ?Closure(array<string, mixed>): bool
     */
    private function buildFilterClosure(?string $filter): ?Closure
    {
        if ($filter === null) {
            return null;
        }

        $needle = mb_strtolower($filter);

        return function (array $case) use ($needle): bool {
            $meta = $case['meta'] ?? null;
            if (is_array($meta)) {
                $name = $meta['name'] ?? null;
                if (is_string($name) && $name !== '') {
                    return str_contains(mb_strtolower($name), $needle);
                }
            }

            $input = $case['input'] ?? null;
            if (is_string($input)) {
                return str_contains(mb_strtolower($input), $needle);
            }

            $encoded = json_encode($input);
            $haystack = $encoded === false ? get_debug_type($input) : $encoded;

            return str_contains(mb_strtolower($haystack), $needle);
        };
    }

    private function printRun(EvalRun $run): void
    {
        $errorCount = 0;
        foreach ($run->results as $index => $result) {
            $label = $this->caseLabel($result, $index);
            $duration = $this->formatDuration($result->durationMs);

            if ($result->error !== null) {
                $errorCount++;
                $this->line(sprintf('  [ERR ] %s  (%s)', $label, $duration));
                $this->line('    '.$result->error->getMessage());

                continue;
            }

            if ($result->failed()) {
                $this->line(sprintf('  [FAIL] %s  (%s)', $label, $duration));
                foreach ($result->assertionResults as $assertion) {
                    if (! $assertion->passed) {
                        $this->line(sprintf(
                            '    %s: %s',
                            $this->assertionName($assertion),
                            $assertion->reason,
                        ));
                    }
                }

                continue;
            }

            $this->line(sprintf('  [PASS] %s  (%s)', $label, $duration));
        }

        $this->line('');
        $this->line(sprintf(
            '  Summary: %d/%d passed, %d error%s, %s total',
            $run->passedCount(),
            $run->total(),
            $errorCount,
            $errorCount === 1 ? '' : 's',
            $this->formatDuration($run->durationMs),
        ));
    }

    private function caseLabel(EvalResult $result, int $index): string
    {
        $meta = $result->case['meta'] ?? null;
        if (is_array($meta)) {
            $name = $meta['name'] ?? null;
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return 'case_'.$index;
    }

    private function assertionName(AssertionResult $assertion): string
    {
        $name = $assertion->metadata['assertion_name'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return 'assertion';
    }

    private function formatDuration(float $ms): string
    {
        if ($ms < 1.0) {
            return number_format($ms, 2, '.', '').'ms';
        }

        return ((int) round($ms)).'ms';
    }

    private function assertionsHeader(EvalSuite $suite, Dataset $dataset): string
    {
        $cases = $dataset->count();
        $baseCount = count($suite->assertions());

        $reflection = new \ReflectionMethod($suite, 'assertionsFor');
        $isOverridden = $reflection->getDeclaringClass()->getName() !== EvalSuite::class;

        $label = $isOverridden
            ? sprintf('%d base assertions (per-case may vary)', $baseCount)
            : sprintf('%d assertions per case', $baseCount);

        return sprintf('  %d cases, %s', $cases, $label);
    }

    private function junitPathFor(string $basePath, EvalSuite $suite, bool $multiple): string
    {
        if (! $multiple) {
            return $basePath;
        }

        $dir = dirname($basePath);
        $filename = basename($basePath);
        $dotPos = strrpos($filename, '.');
        if ($dotPos === false || $dotPos === 0) {
            $stem = $filename;
            $ext = '';
        } else {
            $stem = substr($filename, 0, $dotPos);
            $ext = substr($filename, $dotPos);
        }

        $suffix = strtr($suite->name(), '\\', '_');

        return $dir.'/'.$stem.'.'.$suffix.$ext;
    }
}

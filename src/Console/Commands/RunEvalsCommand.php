<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Console\Commands;

use Illuminate\Console\Command;
use Mosaiqo\Proofread\Jobs\RunEvalSuiteJob;
use Mosaiqo\Proofread\Judge\JudgeAgent;
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
        {--fake-judge= : Fake the judge agent for Rubric assertions: "pass", "fail", or a JSON file path}';

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

        if ($fakeJudgeSpec !== null && ! $this->applyFakeJudge($fakeJudgeSpec)) {
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

        foreach ($suites as $suite) {
            $this->line('Running '.$suite->name());

            $suite->setUp();

            try {
                $dataset = $this->applyFilter($suite->dataset(), $filter);

                if ($dataset === null) {
                    $this->line('  No cases matching filter');
                    $executed++;

                    continue;
                }

                if ($dataset->isEmpty()) {
                    $this->line('  No cases to run');
                    $executed++;

                    continue;
                }

                $this->line($this->assertionsHeader($suite, $dataset));
                $this->line('');

                try {
                    $run = $runner->run($suite->subject(), $dataset, $suite->assertions());
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
            } finally {
                $suite->tearDown();
            }

            $this->printRun($run);

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

        $exit = $anyFailure ? 1 : 0;
        $failedSuites = $anyFailure ? 1 : 0;
        $this->line(sprintf(
            'Overall: %d suite(s) executed, %s, exit %d',
            $executed,
            $failedSuites === 0 ? 'all passed' : '1 suite failed',
            $exit,
        ));

        return $exit;
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

    private function applyFakeJudge(string $spec): bool
    {
        if ($spec === 'pass') {
            JudgeAgent::fake(static fn (): string => (string) json_encode([
                'passed' => true,
                'score' => 1.0,
                'reason' => 'Auto-passed by --fake-judge',
            ]));
            $this->line(sprintf(
                'Using --fake-judge=%s — all Rubric assertions will auto-pass.',
                $spec,
            ));

            return true;
        }

        if ($spec === 'fail') {
            JudgeAgent::fake(static fn (): string => (string) json_encode([
                'passed' => false,
                'score' => 0.1,
                'reason' => 'Auto-failed by --fake-judge',
            ]));
            $this->line(sprintf(
                'Using --fake-judge=%s — all Rubric assertions will auto-fail.',
                $spec,
            ));

            return true;
        }

        if (! file_exists($spec)) {
            $this->error(sprintf(
                "--fake-judge spec '%s' is not 'pass', 'fail', or an existing file path",
                $spec,
            ));

            return false;
        }

        $contents = file_get_contents($spec);
        if ($contents === false) {
            $this->error(sprintf('--fake-judge file "%s" could not be read', $spec));

            return false;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            $this->error(sprintf('--fake-judge file "%s" does not contain a JSON array', $spec));

            return false;
        }

        /** @var list<string> $responses */
        $responses = [];
        foreach ($decoded as $index => $entry) {
            $encoded = json_encode($entry);
            if ($encoded === false) {
                $this->error(sprintf(
                    '--fake-judge file "%s" entry at index %s could not be encoded',
                    $spec,
                    (string) $index,
                ));

                return false;
            }
            $responses[] = $encoded;
        }

        $cursor = 0;
        $count = count($responses);
        JudgeAgent::fake(static function () use ($responses, &$cursor, $count, $spec): string {
            if ($cursor >= $count) {
                throw new \RuntimeException(sprintf(
                    '--fake-judge file "%s" ran out of responses after %d invocation(s)',
                    $spec,
                    $count,
                ));
            }

            $response = $responses[$cursor];
            $cursor++;

            return $response;
        });

        $this->line(sprintf(
            'Using --fake-judge=%s — %d scripted response(s) loaded.',
            $spec,
            $count,
        ));

        return true;
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

    private function applyFilter(Dataset $dataset, ?string $filter): ?Dataset
    {
        if ($filter === null) {
            return $dataset;
        }

        $needle = mb_strtolower($filter);
        $filtered = [];
        foreach ($dataset->cases as $case) {
            $candidate = $this->filterCandidate($case);
            if (str_contains(mb_strtolower($candidate), $needle)) {
                $filtered[] = $case;
            }
        }

        if ($filtered === []) {
            return null;
        }

        return Dataset::make($dataset->name, $filtered);
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private function filterCandidate(array $case): string
    {
        $meta = $case['meta'] ?? null;
        if (is_array($meta)) {
            $name = $meta['name'] ?? null;
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        $input = $case['input'] ?? null;
        if (is_string($input)) {
            return $input;
        }

        $encoded = json_encode($input);

        return $encoded === false ? get_debug_type($input) : $encoded;
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
        $min = PHP_INT_MAX;
        $max = 0;
        foreach ($dataset->cases as $case) {
            $count = count($suite->assertionsFor($case));
            if ($count < $min) {
                $min = $count;
            }
            if ($count > $max) {
                $max = $count;
            }
        }

        $label = $min === $max
            ? sprintf('%d assertions per case', $min)
            : sprintf('%d-%d assertions per case', $min, $max);

        return sprintf('  %d cases, %s', $dataset->count(), $label);
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

<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Mcp\Tools;

use Illuminate\Container\Container;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Mosaiqo\Proofread\Runner\EvalPersister;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

/**
 * MCP tool that runs a Proofread EvalSuite end-to-end and returns a summary
 * of the results, optionally persisting the run to the database.
 */
final class RunEvalSuiteTool extends Tool
{
    protected string $name = 'run_eval_suite';

    protected string $description = 'Run a Proofread EvalSuite end-to-end and return the results summary.';

    private const FAILURE_LIMIT = 10;

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suite_class' => $schema->string()
                ->description('Fully-qualified class name of the EvalSuite to run.')
                ->required(),
            'persist' => $schema->boolean()
                ->description('If true, persist the run to the database via EvalPersister.'),
            'commit_sha' => $schema->string()
                ->description('Git commit SHA to associate with the persisted run.'),
        ];
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        $suiteClass = $request->get('suite_class');
        $persist = (bool) $request->get('persist', false);
        $commitShaRaw = $request->get('commit_sha');
        $commitSha = is_string($commitShaRaw) && $commitShaRaw !== '' ? $commitShaRaw : null;

        if (! is_string($suiteClass) || $suiteClass === '') {
            return Response::error('The suite_class argument is required.');
        }

        if (! class_exists($suiteClass)) {
            return Response::error(sprintf('Suite class "%s" does not exist.', $suiteClass));
        }

        if (! is_subclass_of($suiteClass, EvalSuite::class)) {
            return Response::error(sprintf(
                'Class "%s" does not extend %s.',
                $suiteClass,
                EvalSuite::class,
            ));
        }

        $container = Container::getInstance();

        /** @var EvalSuite $suite */
        $suite = $container->make($suiteClass);

        $runner = $container->make(EvalRunner::class);
        $run = $runner->runSuite($suite);

        $persistedRunId = null;
        if ($persist) {
            $persister = $container->make(EvalPersister::class);
            $model = $persister->persist($run, suiteClass: $suiteClass, commitSha: $commitSha);
            $persistedRunId = $model->id;
        }

        return Response::structured($this->buildPayload(
            $suiteClass,
            $run,
            $persistedRunId,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $suiteClass, EvalRun $run, ?string $persistedRunId): array
    {
        $failures = $this->collectFailures($run);
        $failureCount = count($failures);
        $truncated = $failureCount > self::FAILURE_LIMIT;
        $visibleFailures = $truncated
            ? array_slice($failures, 0, self::FAILURE_LIMIT)
            : $failures;

        $payload = [
            'suite_class' => $suiteClass,
            'dataset_name' => $run->dataset->name,
            'passed' => $run->passed(),
            'total_cases' => $run->total(),
            'passed_count' => $run->passedCount(),
            'failed_count' => $run->failedCount(),
            'duration_ms' => $run->durationMs,
            'total_cost_usd' => $this->totalCost($run),
            'persisted_run_id' => $persistedRunId,
            'failures' => $visibleFailures,
        ];

        if ($truncated) {
            $payload['failures_truncated'] = true;
            $payload['failures_omitted'] = $failureCount - self::FAILURE_LIMIT;
        }

        return $payload;
    }

    /**
     * @return list<array{case_index: int, case_name: string|null, assertions_failed: list<string>}>
     */
    private function collectFailures(EvalRun $run): array
    {
        $failures = [];
        foreach ($run->results as $index => $result) {
            if ($result->passed()) {
                continue;
            }

            $failures[] = [
                'case_index' => $index,
                'case_name' => $this->caseName($result),
                'assertions_failed' => $this->failureReasons($result),
            ];
        }

        return $failures;
    }

    private function caseName(EvalResult $result): ?string
    {
        $meta = $result->case['meta'] ?? null;
        if (is_array($meta)) {
            $name = $meta['name'] ?? null;
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function failureReasons(EvalResult $result): array
    {
        if ($result->error !== null) {
            return [sprintf('error: %s', $result->error->getMessage())];
        }

        $reasons = [];
        foreach ($result->assertionResults as $assertion) {
            if ($assertion->passed) {
                continue;
            }
            $reasons[] = sprintf(
                '%s: %s',
                $this->assertionName($assertion),
                $assertion->reason ?? 'failed',
            );
        }

        return $reasons;
    }

    private function assertionName(AssertionResult $assertion): string
    {
        $name = $assertion->metadata['assertion_name'] ?? null;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return 'assertion';
    }

    private function totalCost(EvalRun $run): ?float
    {
        $total = null;
        foreach ($run->results as $result) {
            foreach ($result->assertionResults as $assertion) {
                $cost = $assertion->metadata['cost_usd'] ?? null;
                if (is_int($cost) || is_float($cost)) {
                    $total = ($total ?? 0.0) + (float) $cost;
                }
            }
        }

        return $total;
    }
}

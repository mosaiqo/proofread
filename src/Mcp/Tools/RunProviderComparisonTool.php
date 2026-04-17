<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Mcp\Tools;

use Illuminate\Container\Container;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Mosaiqo\Proofread\Runner\ComparisonPersister;
use Mosaiqo\Proofread\Runner\ComparisonRunner;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\EvalComparison;
use Mosaiqo\Proofread\Support\EvalRun;

/**
 * MCP tool that runs a Proofread MultiSubjectEvalSuite through the
 * ComparisonRunner and returns per-subject aggregate stats.
 *
 * The response intentionally omits the full cases x subjects matrix: that
 * payload is large and better consumed via the dashboard or evals:export.
 */
final class RunProviderComparisonTool extends Tool
{
    protected string $name = 'run_provider_comparison';

    protected string $description = 'Run a Proofread MultiSubjectEvalSuite and return the matrix of cases x subjects as per-subject aggregate stats (pass rate, cost, duration, avg latency). The full per-case matrix is omitted; use the dashboard or evals:export for that.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suite_class' => $schema->string()
                ->description('Fully-qualified class name of the MultiSubjectEvalSuite to run.')
                ->required(),
            'persist' => $schema->boolean()
                ->description('If true, persist the comparison and its runs to the database.'),
            'commit_sha' => $schema->string()
                ->description('Git commit SHA to associate with the persisted comparison.'),
            'provider_concurrency' => $schema->integer()
                ->description('Number of subjects to run in parallel. 0 (default) runs all subjects in parallel; 1 runs sequentially.'),
        ];
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        $suiteClass = $request->get('suite_class');
        $persist = (bool) $request->get('persist', false);
        $commitShaRaw = $request->get('commit_sha');
        $commitSha = is_string($commitShaRaw) && $commitShaRaw !== '' ? $commitShaRaw : null;
        $providerConcurrencyRaw = $request->get('provider_concurrency', 0);
        $providerConcurrency = is_int($providerConcurrencyRaw)
            ? max(0, $providerConcurrencyRaw)
            : 0;

        if (! is_string($suiteClass) || $suiteClass === '') {
            return Response::error('The suite_class argument is required.');
        }

        if (! class_exists($suiteClass)) {
            return Response::error(sprintf('Suite class "%s" does not exist.', $suiteClass));
        }

        if (! is_subclass_of($suiteClass, MultiSubjectEvalSuite::class)) {
            return Response::error(sprintf(
                'Class "%s" does not extend %s.',
                $suiteClass,
                MultiSubjectEvalSuite::class,
            ));
        }

        $container = Container::getInstance();

        /** @var MultiSubjectEvalSuite $suite */
        $suite = $container->make($suiteClass);

        $runner = $container->make(ComparisonRunner::class);
        $comparison = $runner->run($suite, providerConcurrency: $providerConcurrency);

        $persistedComparisonId = null;
        if ($persist) {
            $persister = $container->make(ComparisonPersister::class);
            $model = $persister->persist($comparison, suiteClass: $suiteClass, commitSha: $commitSha);
            $persistedComparisonId = $model->id;
        }

        return Response::structured($this->buildPayload(
            $suiteClass,
            $comparison,
            $persistedComparisonId,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        string $suiteClass,
        EvalComparison $comparison,
        ?string $persistedComparisonId,
    ): array {
        $labels = $comparison->subjectLabels();
        $passRates = $comparison->passRates();
        $costs = $comparison->totalCosts();

        $runs = [];
        foreach ($labels as $label) {
            $run = $comparison->runForSubject($label);
            if ($run === null) {
                continue;
            }

            $runs[] = [
                'subject_label' => $label,
                'passed' => $run->passed(),
                'total_cases' => $run->total(),
                'passed_cases' => $run->passedCount(),
                'failed_cases' => $run->failedCount(),
                'pass_rate' => round($passRates[$label] ?? 0.0, 3),
                'cost_usd' => $costs[$label] ?? null,
                'duration_ms' => $run->durationMs,
                'avg_latency_ms' => $this->averageLatency($run),
            ];
        }

        return [
            'suite_class' => $suiteClass,
            'name' => $comparison->name,
            'dataset_name' => $comparison->dataset->name,
            'passed' => $comparison->passed(),
            'total_cases' => $comparison->dataset->count(),
            'duration_ms' => $comparison->durationMs,
            'persisted_comparison_id' => $persistedComparisonId,
            'subjects' => $labels,
            'runs' => $runs,
        ];
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
}

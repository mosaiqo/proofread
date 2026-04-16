<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

use Illuminate\Support\Facades\DB;
use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Models\EvalDataset as EvalDatasetModel;
use Mosaiqo\Proofread\Models\EvalResult as EvalResultModel;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

/**
 * Converts an in-memory EvalRun value object into persisted Eloquent rows.
 *
 * Consumes the immutable Support\EvalRun without mutating it. The entire
 * operation runs inside a single DB transaction so partial runs never leak
 * into the database.
 */
class EvalPersister
{
    public function persist(
        EvalRun $run,
        ?string $suiteClass = null,
        ?string $commitSha = null,
        ?string $subjectType = null,
        ?string $subjectClass = null,
    ): EvalRunModel {
        $runModel = DB::transaction(function () use ($run, $suiteClass, $commitSha, $subjectType, $subjectClass): EvalRunModel {
            $dataset = $this->upsertDataset($run);
            $runModel = $this->insertRun($run, $dataset, $suiteClass, $commitSha, $subjectType, $subjectClass);

            foreach ($run->results as $index => $result) {
                $attrs = $this->buildResultAttributes($runModel->id, $index, $result);
                $model = new EvalResultModel;
                $model->fill($attrs);
                $model->save();
            }

            return $runModel;
        });

        event(new EvalRunPersisted($runModel));

        return $runModel;
    }

    private function upsertDataset(EvalRun $run): EvalDatasetModel
    {
        $checksum = $this->checksumFor($run);

        $dataset = EvalDatasetModel::query()->where('name', $run->dataset->name)->first();
        if ($dataset === null) {
            $dataset = new EvalDatasetModel;
            $dataset->fill([
                'name' => $run->dataset->name,
                'case_count' => $run->dataset->count(),
                'checksum' => $checksum,
            ]);
            $dataset->save();

            return $dataset;
        }

        $dataset->fill([
            'case_count' => $run->dataset->count(),
            'checksum' => $checksum,
        ]);
        $dataset->save();

        return $dataset;
    }

    private function insertRun(
        EvalRun $run,
        EvalDatasetModel $dataset,
        ?string $suiteClass,
        ?string $commitSha,
        ?string $subjectType,
        ?string $subjectClass,
    ): EvalRunModel {
        $aggregates = $this->aggregateRunMetrics($run);

        $model = new EvalRunModel;
        $model->fill([
            'dataset_id' => $dataset->id,
            'dataset_name' => $dataset->name,
            'suite_class' => $suiteClass,
            'subject_type' => $subjectType ?? 'unknown',
            'subject_class' => $subjectClass,
            'commit_sha' => $commitSha,
            'model' => $aggregates['model'],
            'passed' => $run->passed(),
            'pass_count' => $aggregates['pass_count'],
            'fail_count' => $aggregates['fail_count'],
            'error_count' => $aggregates['error_count'],
            'total_count' => $run->total(),
            'duration_ms' => $run->durationMs,
            'total_cost_usd' => $aggregates['total_cost_usd'],
            'total_tokens_in' => $aggregates['total_tokens_in'],
            'total_tokens_out' => $aggregates['total_tokens_out'],
        ]);
        $model->save();

        return $model;
    }

    /**
     * @return array{
     *     pass_count: int,
     *     fail_count: int,
     *     error_count: int,
     *     total_cost_usd: float|null,
     *     total_tokens_in: int|null,
     *     total_tokens_out: int|null,
     *     model: string|null,
     * }
     */
    private function aggregateRunMetrics(EvalRun $run): array
    {
        $passCount = 0;
        $failCount = 0;
        $errorCount = 0;
        $totalCost = null;
        $totalIn = null;
        $totalOut = null;
        $model = null;

        foreach ($run->results as $result) {
            if ($result->hasError()) {
                $errorCount++;
            } elseif ($result->passed()) {
                $passCount++;
            } else {
                $failCount++;
            }

            $metrics = $this->extractResultMetrics($result);
            if ($metrics['cost_usd'] !== null) {
                $totalCost = ($totalCost ?? 0.0) + $metrics['cost_usd'];
            }
            if ($metrics['tokens_in'] !== null) {
                $totalIn = ($totalIn ?? 0) + $metrics['tokens_in'];
            }
            if ($metrics['tokens_out'] !== null) {
                $totalOut = ($totalOut ?? 0) + $metrics['tokens_out'];
            }
            if ($model === null && $metrics['model'] !== null) {
                $model = $metrics['model'];
            }
        }

        return [
            'pass_count' => $passCount,
            'fail_count' => $failCount,
            'error_count' => $errorCount,
            'total_cost_usd' => $totalCost,
            'total_tokens_in' => $totalIn,
            'total_tokens_out' => $totalOut,
            'model' => $model,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildResultAttributes(string $runId, int $index, EvalResult $result): array
    {
        $metrics = $this->extractResultMetrics($result);

        return [
            'run_id' => $runId,
            'case_index' => $index,
            'case_name' => $this->caseName($result),
            'input' => $result->case['input'] ?? null,
            'output' => is_string($result->output) ? $result->output : $this->stringifyOutput($result->output),
            'expected' => $this->expectedFor($result),
            'passed' => $result->passed(),
            'assertion_results' => $this->serializeAssertions($result->assertionResults),
            'error_class' => $result->error !== null ? $result->error::class : null,
            'error_message' => $result->error?->getMessage(),
            'error_trace' => $result->error?->getTraceAsString(),
            'duration_ms' => $result->durationMs,
            'latency_ms' => $metrics['latency_ms'],
            'tokens_in' => $metrics['tokens_in'],
            'tokens_out' => $metrics['tokens_out'],
            'cost_usd' => $metrics['cost_usd'],
            'model' => $metrics['model'],
        ];
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
     * @return array<string, mixed>|null
     */
    private function expectedFor(EvalResult $result): ?array
    {
        $expected = $result->case['expected'] ?? null;
        if (is_array($expected)) {
            /** @var array<string, mixed> $expected */
            return $expected;
        }

        if ($expected === null) {
            return null;
        }

        return ['value' => $expected];
    }

    private function stringifyOutput(mixed $output): ?string
    {
        if ($output === null) {
            return null;
        }

        if (is_scalar($output)) {
            return (string) $output;
        }

        $encoded = json_encode($output);

        return $encoded === false ? null : $encoded;
    }

    /**
     * @param  list<AssertionResult>  $assertions
     * @return list<array<string, mixed>>
     */
    private function serializeAssertions(array $assertions): array
    {
        $out = [];
        foreach ($assertions as $assertion) {
            $name = $assertion->metadata['assertion_name'] ?? null;
            $metadata = $assertion->metadata;
            unset($metadata['assertion_name']);

            $out[] = [
                'name' => is_string($name) && $name !== '' ? $name : 'unknown',
                'passed' => $assertion->passed,
                'reason' => $assertion->reason,
                'score' => $assertion->score,
                'metadata' => $metadata,
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *     latency_ms: float|null,
     *     tokens_in: int|null,
     *     tokens_out: int|null,
     *     cost_usd: float|null,
     *     model: string|null,
     * }
     */
    private function extractResultMetrics(EvalResult $result): array
    {
        $latency = null;
        $tokensIn = null;
        $tokensOut = null;
        $cost = null;
        $model = null;

        foreach ($result->assertionResults as $assertion) {
            $meta = $assertion->metadata;

            if ($latency === null && isset($meta['latency_ms']) && (is_int($meta['latency_ms']) || is_float($meta['latency_ms']))) {
                $latency = (float) $meta['latency_ms'];
            }
            if ($tokensIn === null && isset($meta['tokens_in']) && is_int($meta['tokens_in'])) {
                $tokensIn = $meta['tokens_in'];
            }
            if ($tokensOut === null && isset($meta['tokens_out']) && is_int($meta['tokens_out'])) {
                $tokensOut = $meta['tokens_out'];
            }
            if ($cost === null && isset($meta['cost_usd']) && (is_int($meta['cost_usd']) || is_float($meta['cost_usd']))) {
                $cost = (float) $meta['cost_usd'];
            }
            if ($model === null && isset($meta['model']) && is_string($meta['model']) && $meta['model'] !== '') {
                $model = $meta['model'];
            }
        }

        return [
            'latency_ms' => $latency,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'cost_usd' => $cost,
            'model' => $model,
        ];
    }

    private function checksumFor(EvalRun $run): string
    {
        $encoded = json_encode($run->dataset->cases);

        return hash('sha256', $encoded === false ? $run->dataset->name : $encoded);
    }
}

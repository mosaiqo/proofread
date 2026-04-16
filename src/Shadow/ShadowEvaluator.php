<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

use Illuminate\Support\Carbon;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Models\ShadowCapture;
use Mosaiqo\Proofread\Models\ShadowEval;
use Mosaiqo\Proofread\Support\AssertionResult;
use Throwable;

final class ShadowEvaluator
{
    public function __construct(
        private readonly ShadowAssertionsRegistry $registry,
    ) {}

    /**
     * @param  iterable<ShadowCapture>  $captures
     */
    public function evaluate(iterable $captures, bool $force = false): ShadowEvaluationSummary
    {
        $start = hrtime(true);

        $processed = 0;
        $skipped = 0;
        $evalsCreated = 0;
        $passed = 0;
        $failed = 0;
        $totalJudgeCost = 0.0;

        foreach ($captures as $capture) {
            $processed++;

            $eval = $this->evaluateOne($capture, $force);
            if ($eval === null) {
                $skipped++;

                continue;
            }

            $evalsCreated++;
            if ($eval->passed) {
                $passed++;
            } else {
                $failed++;
            }

            if ($eval->judge_cost_usd !== null) {
                $totalJudgeCost += $eval->judge_cost_usd;
            }
        }

        $duration = (hrtime(true) - $start) / 1_000_000;

        return new ShadowEvaluationSummary(
            capturesProcessed: $processed,
            capturesSkipped: $skipped,
            evalsCreated: $evalsCreated,
            passed: $passed,
            failed: $failed,
            totalJudgeCostUsd: $totalJudgeCost,
            durationMs: round($duration, 3),
        );
    }

    public function evaluateOne(ShadowCapture $capture, bool $force = false): ?ShadowEval
    {
        $existing = ShadowEval::query()
            ->where('capture_id', $capture->id)
            ->first();

        if ($existing !== null && ! $force) {
            return $existing;
        }

        if (! $this->registry->hasAssertionsFor($capture->agent_class)) {
            return null;
        }

        if ($existing !== null) {
            $existing->delete();
        }

        $assertions = $this->registry->forAgent($capture->agent_class);

        $context = [
            'case_index' => 0,
            'latency_ms' => $capture->latency_ms,
            'tokens_in' => $capture->tokens_in,
            'tokens_out' => $capture->tokens_out,
            'cost_usd' => $capture->cost_usd,
            'model' => $capture->model_used,
            'input' => $capture->input_payload,
            'captured_at' => $capture->captured_at,
            'shadow_capture_id' => $capture->id,
        ];

        $output = $capture->output ?? '';

        $start = hrtime(true);
        $results = [];
        foreach ($assertions as $assertion) {
            $results[] = $this->runAssertion($assertion, $output, $context);
        }
        $duration = (hrtime(true) - $start) / 1_000_000;

        return $this->persistEval($capture, $results, $duration);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function runAssertion(Assertion $assertion, mixed $output, array $context): AssertionResult
    {
        try {
            $result = $assertion->run($output, $context);
        } catch (Throwable $e) {
            return AssertionResult::fail(
                sprintf('Assertion %s threw: %s', $assertion->name(), $e->getMessage()),
                null,
                [
                    'assertion_name' => $assertion->name(),
                    'exception_class' => $e::class,
                ],
            );
        }

        if (! array_key_exists('assertion_name', $result->metadata)) {
            $metadata = $result->metadata + ['assertion_name' => $assertion->name()];

            return $result->passed
                ? AssertionResult::pass($result->reason, $result->score, $metadata)
                : AssertionResult::fail($result->reason, $result->score, $metadata);
        }

        return $result;
    }

    /**
     * @param  list<AssertionResult>  $results
     */
    private function persistEval(ShadowCapture $capture, array $results, float $durationMs): ShadowEval
    {
        $total = count($results);
        $passedCount = 0;
        $judgeCost = null;
        $judgeTokensIn = null;
        $judgeTokensOut = null;
        $serialized = [];

        foreach ($results as $result) {
            if ($result->passed) {
                $passedCount++;
            }

            $judgeCost = $this->accumulateFloat($judgeCost, $result->metadata['judge_cost_usd'] ?? null);
            $judgeTokensIn = $this->accumulateInt($judgeTokensIn, $result->metadata['judge_tokens_in'] ?? null);
            $judgeTokensOut = $this->accumulateInt($judgeTokensOut, $result->metadata['judge_tokens_out'] ?? null);

            $name = $result->metadata['assertion_name'] ?? null;
            $serialized[] = [
                'name' => is_string($name) && $name !== '' ? $name : 'assertion',
                'passed' => $result->passed,
                'reason' => $result->reason,
                'score' => $result->score,
                'metadata' => $result->metadata,
            ];
        }

        $failedCount = $total - $passedCount;

        $eval = new ShadowEval;
        $eval->fill([
            'capture_id' => $capture->id,
            'agent_class' => $capture->agent_class,
            'passed' => $failedCount === 0,
            'total_assertions' => $total,
            'passed_assertions' => $passedCount,
            'failed_assertions' => $failedCount,
            'assertion_results' => $serialized,
            'judge_cost_usd' => $judgeCost,
            'judge_tokens_in' => $judgeTokensIn,
            'judge_tokens_out' => $judgeTokensOut,
            'evaluation_duration_ms' => round($durationMs, 3),
            'evaluated_at' => Carbon::now(),
        ]);
        $eval->save();

        return $eval;
    }

    private function accumulateFloat(?float $current, mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value)) {
            return $current;
        }

        return ($current ?? 0.0) + (float) $value;
    }

    private function accumulateInt(?int $current, mixed $value): ?int
    {
        if (! is_int($value)) {
            return $current;
        }

        return ($current ?? 0) + $value;
    }
}

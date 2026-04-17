<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Otel;

use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;

/**
 * Emits a retrospective OpenTelemetry span tree for a persisted
 * eval run: a root span for the run, a child span per case, and
 * assertion outcomes as events on the case spans.
 *
 * Registered conditionally by ProofreadServiceProvider only when
 * open-telemetry/api is installed. Spans propagate to whatever
 * TracerProvider the host app has configured; without one, OTel
 * defaults to a no-op provider.
 */
final class EvalRunTracer
{
    private const ERROR_MESSAGE_MAX_LENGTH = 500;

    public function __construct(
        private readonly TracerInterface $tracer,
    ) {}

    public function handle(EvalRunPersisted $event): void
    {
        $run = $event->run;

        $startNanos = $this->runStartNanos($run);
        $endNanos = $startNanos + $this->msToNanos($run->duration_ms);

        $rootSpan = $this->tracer
            ->spanBuilder('proofread.eval.run')
            ->setParent(false)
            ->setStartTimestamp($startNanos)
            ->setAttributes($this->runAttributes($run))
            ->startSpan();

        $rootSpan->setStatus($run->passed ? StatusCode::STATUS_OK : StatusCode::STATUS_ERROR);

        $rootContext = Context::getCurrent()->withContextValue($rootSpan);

        $cursorNanos = $startNanos;
        foreach ($run->results as $result) {
            $caseDurationNanos = $this->msToNanos($result->duration_ms);
            $caseEndNanos = $cursorNanos + $caseDurationNanos;
            if ($caseEndNanos > $endNanos) {
                $caseEndNanos = $endNanos;
            }

            $this->recordCaseSpan($result, $rootContext, $cursorNanos, $caseEndNanos);

            $cursorNanos = $caseEndNanos;
        }

        $rootSpan->end($endNanos);
    }

    private function recordCaseSpan(
        EvalResult $result,
        ContextInterface $parentContext,
        int $startNanos,
        int $endNanos,
    ): void {
        $caseSpan = $this->tracer
            ->spanBuilder('proofread.eval.case')
            ->setParent($parentContext)
            ->setStartTimestamp($startNanos)
            ->setAttributes($this->caseAttributes($result))
            ->startSpan();

        $this->addAssertionEvents($caseSpan, $result, $startNanos);

        if ($result->error_class !== null) {
            $caseSpan->setStatus(StatusCode::STATUS_ERROR, $result->error_message);
        } else {
            $caseSpan->setStatus($result->passed ? StatusCode::STATUS_OK : StatusCode::STATUS_ERROR);
        }

        $caseSpan->end($endNanos);
    }

    /**
     * @return array<non-empty-string, bool|int|float|string|null>
     */
    private function runAttributes(EvalRun $run): array
    {
        $attributes = [
            'proofread.run.id' => $run->id,
            'proofread.dataset.name' => $run->dataset_name,
            'proofread.suite.class' => $run->suite_class,
            'proofread.subject.class' => $run->subject_class,
            'proofread.run.passed' => $run->passed,
            'proofread.run.pass_count' => $run->pass_count,
            'proofread.run.fail_count' => $run->fail_count,
            'proofread.run.total_count' => $run->total_count,
            'proofread.run.total_cost_usd' => $run->total_cost_usd,
            'proofread.run.total_tokens_in' => $run->total_tokens_in,
            'proofread.run.total_tokens_out' => $run->total_tokens_out,
            'proofread.run.model' => $run->model,
            'proofread.run.commit_sha' => $run->commit_sha,
            'proofread.run.comparison_id' => $run->comparison_id,
            'proofread.run.subject_label' => $run->subject_label,
        ];

        return array_filter(
            $attributes,
            static fn ($value): bool => $value !== null,
        );
    }

    /**
     * @return array<non-empty-string, bool|int|float|string|null>
     */
    private function caseAttributes(EvalResult $result): array
    {
        $errorMessage = $result->error_message;
        if (is_string($errorMessage) && strlen($errorMessage) > self::ERROR_MESSAGE_MAX_LENGTH) {
            $errorMessage = substr($errorMessage, 0, self::ERROR_MESSAGE_MAX_LENGTH);
        }

        $attributes = [
            'proofread.case.index' => $result->case_index,
            'proofread.case.name' => $result->case_name,
            'proofread.case.passed' => $result->passed,
            'proofread.case.duration_ms' => $result->duration_ms,
            'proofread.case.cost_usd' => $result->cost_usd,
            'proofread.case.tokens_in' => $result->tokens_in,
            'proofread.case.tokens_out' => $result->tokens_out,
            'proofread.case.latency_ms' => $result->latency_ms,
            'proofread.case.model' => $result->model,
            'proofread.case.error_class' => $result->error_class,
            'proofread.case.error_message' => $errorMessage,
        ];

        return array_filter(
            $attributes,
            static fn ($value): bool => $value !== null,
        );
    }

    private function addAssertionEvents(SpanInterface $span, EvalResult $result, int $timestampNanos): void
    {
        $assertions = $result->assertion_results;

        foreach ($assertions as $assertion) {
            $attributes = [];
            foreach (['name', 'passed', 'reason', 'score'] as $key) {
                if (! array_key_exists($key, $assertion)) {
                    continue;
                }
                $value = $assertion[$key];
                if ($value === null) {
                    continue;
                }
                if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
                    $attributes[$key] = $value;
                }
            }

            $span->addEvent('assertion', $attributes, $timestampNanos);
        }
    }

    private function runStartNanos(EvalRun $run): int
    {
        $createdAt = $run->created_at;
        if ($createdAt === null) {
            return (int) (microtime(true) * 1_000_000_000);
        }

        return $createdAt->getTimestamp() * 1_000_000_000
            + (int) ($createdAt->format('u')) * 1_000;
    }

    private function msToNanos(float $ms): int
    {
        return (int) round($ms * 1_000_000);
    }
}

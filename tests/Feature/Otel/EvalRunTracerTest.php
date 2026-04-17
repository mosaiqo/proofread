<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Mosaiqo\Proofread\Events\EvalRunPersisted;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\Otel\EvalRunTracer;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * @param  list<array<string, mixed>>  $resultsData
 * @param  array<string, mixed>  $runOverrides
 */
function seedOtelRun(string $datasetName, array $resultsData, array $runOverrides = []): EvalRun
{
    $dataset = EvalDataset::query()->firstOrCreate(
        ['name' => $datasetName],
        ['case_count' => count($resultsData), 'checksum' => hash('sha256', $datasetName)],
    );

    $passCount = 0;
    $failCount = 0;
    $errorCount = 0;
    foreach ($resultsData as $row) {
        if (($row['error_class'] ?? null) !== null) {
            $errorCount++;
            $failCount++;
        } elseif (($row['passed'] ?? true) === true) {
            $passCount++;
        } else {
            $failCount++;
        }
    }

    $run = new EvalRun;
    $run->fill(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $datasetName,
        'suite_class' => 'App\\Evals\\OtelSuite',
        'subject_type' => 'agent',
        'subject_class' => 'App\\Agents\\OtelAgent',
        'subject_label' => null,
        'commit_sha' => 'cafe1234',
        'model' => 'claude-haiku-4-5',
        'passed' => $failCount === 0,
        'pass_count' => $passCount,
        'fail_count' => $failCount,
        'error_count' => $errorCount,
        'total_count' => count($resultsData),
        'duration_ms' => 1500.0,
        'total_cost_usd' => 0.0123,
        'total_tokens_in' => 500,
        'total_tokens_out' => 120,
    ], $runOverrides));
    $run->save();

    foreach ($resultsData as $row) {
        $result = new EvalResult;
        $result->fill([
            'run_id' => $run->id,
            'case_index' => $row['case_index'],
            'case_name' => $row['case_name'] ?? null,
            'input' => ['value' => 'x'],
            'output' => null,
            'expected' => null,
            'passed' => ($row['error_class'] ?? null) === null && ($row['passed'] ?? true),
            'assertion_results' => $row['assertion_results'] ?? [],
            'error_class' => $row['error_class'] ?? null,
            'error_message' => $row['error_message'] ?? null,
            'error_trace' => null,
            'duration_ms' => $row['duration_ms'] ?? 50.0,
            'latency_ms' => $row['latency_ms'] ?? null,
            'tokens_in' => $row['tokens_in'] ?? null,
            'tokens_out' => $row['tokens_out'] ?? null,
            'cost_usd' => $row['cost_usd'] ?? null,
            'model' => $row['model'] ?? null,
        ]);
        $result->save();
    }

    return $run->fresh(['results']) ?? $run;
}

beforeEach(function (): void {
    $this->exporter = new InMemoryExporter;
    $this->tracerProvider = new TracerProvider(
        new SimpleSpanProcessor($this->exporter),
    );
    $this->tracer = new EvalRunTracer(
        $this->tracerProvider->getTracer('mosaiqo/proofread', '0.0.0-test'),
    );
});

it('emits a root span for a persisted eval run', function (): void {
    $run = seedOtelRun('otel-basic', [
        ['case_index' => 0, 'passed' => true],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());

    $rootSpans = array_values(array_filter(
        $spans,
        fn (ImmutableSpan $s): bool => $s->getName() === 'proofread.eval.run',
    ));

    expect($rootSpans)->toHaveCount(1);
});

it('includes expected run attributes on the root span', function (): void {
    $run = seedOtelRun('otel-attrs', [
        ['case_index' => 0, 'passed' => true],
    ], [
        'dataset_name' => 'otel-attrs',
        'suite_class' => 'App\\Evals\\AttrSuite',
        'subject_class' => 'App\\Agents\\AttrAgent',
        'subject_label' => 'gpt-4',
        'commit_sha' => 'abcd0123',
        'model' => 'gpt-4o',
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'total_count' => 1,
        'total_cost_usd' => 0.5,
        'total_tokens_in' => 1000,
        'total_tokens_out' => 200,
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $this->exporter->getStorage();
    $spans = array_values($this->exporter->getSpans());
    $root = collect($spans)->first(fn (ImmutableSpan $s) => $s->getName() === 'proofread.eval.run');

    expect($root)->not->toBeNull();
    assert($root instanceof ImmutableSpan);
    $attrs = $root->getAttributes()->toArray();

    expect($attrs)
        ->toHaveKey('proofread.run.id', $run->id)
        ->toHaveKey('proofread.dataset.name', 'otel-attrs')
        ->toHaveKey('proofread.suite.class', 'App\\Evals\\AttrSuite')
        ->toHaveKey('proofread.subject.class', 'App\\Agents\\AttrAgent')
        ->toHaveKey('proofread.run.subject_label', 'gpt-4')
        ->toHaveKey('proofread.run.passed', true)
        ->toHaveKey('proofread.run.pass_count', 1)
        ->toHaveKey('proofread.run.fail_count', 0)
        ->toHaveKey('proofread.run.total_count', 1)
        ->toHaveKey('proofread.run.total_cost_usd', 0.5)
        ->toHaveKey('proofread.run.total_tokens_in', 1000)
        ->toHaveKey('proofread.run.total_tokens_out', 200)
        ->toHaveKey('proofread.run.model', 'gpt-4o')
        ->toHaveKey('proofread.run.commit_sha', 'abcd0123');
});

it('emits a child span for each case', function (): void {
    $run = seedOtelRun('otel-cases', [
        ['case_index' => 0, 'passed' => true, 'case_name' => 'case-a'],
        ['case_index' => 1, 'passed' => true, 'case_name' => 'case-b'],
        ['case_index' => 2, 'passed' => false, 'case_name' => 'case-c'],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());

    $caseSpans = array_values(array_filter(
        $spans,
        fn (ImmutableSpan $s): bool => $s->getName() === 'proofread.eval.case',
    ));

    expect($caseSpans)->toHaveCount(3);
});

it('preserves parent-child relationships between run and case spans', function (): void {
    $run = seedOtelRun('otel-parent', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());

    /** @var ImmutableSpan $root */
    $root = collect($spans)->first(fn (ImmutableSpan $s) => $s->getName() === 'proofread.eval.run');
    $cases = array_values(array_filter(
        $spans,
        fn (ImmutableSpan $s): bool => $s->getName() === 'proofread.eval.case',
    ));

    expect($root)->not->toBeNull();
    foreach ($cases as $case) {
        expect($case->getParentSpanId())->toBe($root->getSpanId());
        expect($case->getTraceId())->toBe($root->getTraceId());
    }
});

it('sets span status to OK when run passed', function (): void {
    $run = seedOtelRun('otel-ok', [
        ['case_index' => 0, 'passed' => true],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());
    /** @var ImmutableSpan $root */
    $root = collect($spans)->first(fn (ImmutableSpan $s) => $s->getName() === 'proofread.eval.run');

    expect($root->getStatus()->getCode())->toBe(StatusCode::STATUS_OK);
});

it('sets span status to ERROR when run failed', function (): void {
    $run = seedOtelRun('otel-err', [
        ['case_index' => 0, 'passed' => false],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());
    /** @var ImmutableSpan $root */
    $root = collect($spans)->first(fn (ImmutableSpan $s) => $s->getName() === 'proofread.eval.run');

    expect($root->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR);
});

it('sets start and end timestamps reflecting the run duration', function (): void {
    $run = seedOtelRun('otel-timing', [
        ['case_index' => 0, 'passed' => true],
    ], ['duration_ms' => 2500.0]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());
    /** @var ImmutableSpan $root */
    $root = collect($spans)->first(fn (ImmutableSpan $s) => $s->getName() === 'proofread.eval.run');

    $durationNanos = $root->getEndEpochNanos() - $root->getStartEpochNanos();
    // 2500ms = 2_500_000_000 ns
    expect($durationNanos)->toBe(2_500_000_000);
});

it('adds assertion events to case spans', function (): void {
    $run = seedOtelRun('otel-events', [
        [
            'case_index' => 0,
            'passed' => true,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => 'ok'],
                ['name' => 'regex', 'passed' => false, 'reason' => 'no match', 'score' => 0.2],
            ],
        ],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());
    /** @var ImmutableSpan $case */
    $case = collect($spans)->first(fn (ImmutableSpan $s) => $s->getName() === 'proofread.eval.case');

    expect($case)->not->toBeNull();
    $events = $case->getEvents();
    expect($events)->toHaveCount(2);

    $names = array_map(fn ($e) => $e->getName(), $events);
    expect($names)->each->toBe('assertion');

    $firstAttrs = $events[0]->getAttributes()->toArray();
    expect($firstAttrs)->toHaveKey('name', 'contains')
        ->toHaveKey('passed', true);
});

it('records error info when a case has an exception', function (): void {
    $run = seedOtelRun('otel-errcase', [
        [
            'case_index' => 0,
            'error_class' => 'RuntimeException',
            'error_message' => 'boom',
        ],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());
    /** @var ImmutableSpan $case */
    $case = collect($spans)->first(fn (ImmutableSpan $s) => $s->getName() === 'proofread.eval.case');

    expect($case)->not->toBeNull();
    $attrs = $case->getAttributes()->toArray();
    expect($attrs)
        ->toHaveKey('proofread.case.error_class', 'RuntimeException')
        ->toHaveKey('proofread.case.error_message', 'boom');
    expect($case->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR);
});

it('registers the listener only when OpenTelemetry API is available', function (): void {
    expect(interface_exists(TracerInterface::class))->toBeTrue();

    expect(Event::hasListeners(EvalRunPersisted::class))->toBeTrue();
});

it('coexists with Telescope and Pulse listeners without interfering', function (): void {
    $listeners = Event::getListeners(EvalRunPersisted::class);
    expect(count($listeners))->toBeGreaterThanOrEqual(1);

    $run = seedOtelRun('otel-coexist', [
        ['case_index' => 0, 'passed' => true],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());
    expect($spans)->not->toBeEmpty();
});

it('assigns each case span a SpanContext with the same trace id as the root', function (): void {
    $run = seedOtelRun('otel-trace-id', [
        ['case_index' => 0, 'passed' => true],
        ['case_index' => 1, 'passed' => true],
    ]);

    $this->tracer->handle(new EvalRunPersisted($run));

    $spans = array_values($this->exporter->getSpans());
    /** @var ImmutableSpan $root */
    $root = collect($spans)->first(fn (ImmutableSpan $s) => $s->getName() === 'proofread.eval.run');
    $cases = array_values(array_filter(
        $spans,
        fn (ImmutableSpan $s): bool => $s->getName() === 'proofread.eval.case',
    ));

    foreach ($cases as $case) {
        expect($case->getContext())->toBeInstanceOf(SpanContextInterface::class);
        expect($case->getTraceId())->toBe($root->getTraceId());
    }
});

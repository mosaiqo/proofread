---
title: "OpenTelemetry"
section: "Integrations"
---

# OpenTelemetry

Persisted eval runs emit an OTel span tree to whatever
`TracerProvider` the host app has configured. Pipe the spans to
Jaeger, Grafana Tempo, Honeycomb, or any OTLP collector and get
waterfall visualizations of every run, every case, and every
assertion.

## Installation

For the API shim alone (the minimum Proofread needs to emit spans):

```bash
composer require open-telemetry/api
```

For a full exporter pipeline:

```bash
composer require open-telemetry/sdk open-telemetry/exporter-otlp
```

Proofread's `EvalRunTracer` is registered conditionally when
`interface_exists(\OpenTelemetry\API\Trace\TracerInterface::class)`
is true. The tracer is resolved from
`Globals::tracerProvider()->getTracer('mosaiqo/proofread', VERSION)`.
Without a configured provider, spans are created against the no-op
tracer and silently discarded.

## Configuring a TracerProvider

A minimal console-export setup in `AppServiceProvider::boot()`:

```php
use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporterFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

$provider = new TracerProvider(
    new SimpleSpanProcessor((new ConsoleSpanExporterFactory)->create()),
);

Globals::registerInitializer(
    fn ($builder) => $builder->withTracerProvider($provider),
);
```

Swap `ConsoleSpanExporterFactory` for an OTLP exporter in production.

## Span structure

```
proofread.eval.run                         (root)
├─ proofread.eval.case [case.index=0]      (child)
│   ├─ event: assertion name=contains passed=true
│   └─ event: assertion name=rubric passed=false reason=...
├─ proofread.eval.case [case.index=1]
│   └─ event: assertion name=regex passed=true
└─ ...
```

One root span per run, one child span per case, and one event per
assertion result attached to its case span. Events carry `name`,
`passed`, `reason`, and `score` when available (only scalar values
are forwarded — arrays and objects in metadata are dropped).

## Attributes reference

### Root span: `proofread.eval.run`

- `proofread.run.id`, `proofread.dataset.name`,
  `proofread.suite.class`, `proofread.subject.class`,
  `proofread.run.subject_label`.
- `proofread.run.passed`, `proofread.run.pass_count`,
  `proofread.run.fail_count`, `proofread.run.total_count`.
- `proofread.run.total_cost_usd`, `proofread.run.total_tokens_in`,
  `proofread.run.total_tokens_out`.
- `proofread.run.model`, `proofread.run.commit_sha`,
  `proofread.run.comparison_id` (nullable).

Null-valued attributes are filtered out before the span is emitted,
so backends see a sparse but never ambiguous payload.

### Case span: `proofread.eval.case`

- `proofread.case.index`, `proofread.case.name`,
  `proofread.case.passed`.
- `proofread.case.duration_ms`, `proofread.case.cost_usd`,
  `proofread.case.tokens_in`, `proofread.case.tokens_out`,
  `proofread.case.latency_ms`.
- `proofread.case.model`, `proofread.case.error_class`,
  `proofread.case.error_message` (truncated to 500 characters).

### Status codes

- Root span: `OK` when the run passed, `ERROR` when it failed.
- Case span: `OK` when the case passed, `ERROR` when it failed. When
  `error_class` is set the error message is attached as the status
  description as well.

## Trace timing

- Root span timestamps match the run's `created_at` +
  `duration_ms`.
- Case spans are laid out **sequentially** within the root span —
  the tracer advances a cursor by each case's `duration_ms`.
- The final case span is clamped to the root's end so the waterfall
  never overshoots the parent.

> **[info]** If your runs use concurrency &gt; 1, case spans still
> display sequentially in the trace. The total root duration is
> accurate; individual case positions are illustrative rather than a
> faithful reproduction of real parallel scheduling.

## Exporting to real backends

OTLP over HTTP (Jaeger, Tempo, Honeycomb, most collectors):

```php
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;

$transport = (new OtlpHttpTransportFactory)->create(
    'https://otlp.yourtracer.com/v1/traces',
    'application/x-protobuf',
);
$exporter = new SpanExporter($transport);
```

Wrap the exporter in a `BatchSpanProcessor` for production traffic
rather than the `SimpleSpanProcessor` used in the quickstart.

## Relationship to run execution

The tracer is retrospective: it fires on `EvalRunPersisted`, which
means transient runs (those not written to the database) do not emit
spans. See [running evals](/docs/running-evals) for when persistence
happens and [persistence](/docs/persistence) for how to opt in.

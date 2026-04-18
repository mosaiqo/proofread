---
title: Multi-provider comparison
section: Guides
---

# Multi-provider comparison

Run the same dataset and assertion stack against multiple Agents —
different models, different providers, different prompt variations —
in parallel. Get a matrix view of how each subject performed, export
the result, and make migration decisions from data rather than gut.

## When to use it

Multi-provider comparison earns its keep when you need a side-by-side:

- **Model migration.** Should you move from Sonnet to Haiku? How much
  pass-rate do you lose, and how much cost do you save?
- **Provider A/B.** Claude vs GPT vs Gemini on the same dataset. The
  same criteria, the same judge, the same seed.
- **Prompt variation.** A single model, two different system prompts,
  identical assertions. Which prompt holds up better?
- **Pre-release regression.** Check a candidate model version against
  the shipped version on a curated dataset before you ship.

## Writing a `MultiSubjectEvalSuite`

Extend `MultiSubjectEvalSuite` instead of `EvalSuite` and declare a
`subjects()` map of label → subject:

```php
<?php

declare(strict_types=1);

namespace App\Evals;

use App\Agents\SentimentAgentHaiku;
use App\Agents\SentimentAgentOpus;
use App\Agents\SentimentAgentSonnet;
use Mosaiqo\Proofread\Assertions\LatencyLimit;
use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class SentimentMatrixSuite extends MultiSubjectEvalSuite
{
    public function name(): string
    {
        return 'sentiment-matrix';
    }

    public function dataset(): Dataset
    {
        return Dataset::fromPath(base_path('tests/Datasets/sentiment.json'));
    }

    public function subjects(): array
    {
        return [
            'haiku'  => SentimentAgentHaiku::class,
            'sonnet' => SentimentAgentSonnet::class,
            'opus'   => SentimentAgentOpus::class,
        ];
    }

    public function assertions(): array
    {
        return [
            Rubric::make('Must classify sentiment correctly.')->minScore(0.8),
            LatencyLimit::under(3_000),
        ];
    }
}
```

Subject labels become column headers in the matrix and are injected
into the per-case context as `subject_label`. This is how you branch
assertions per subject — override `assertionsFor()`:

```php
public function assertionsFor(array $case): array
{
    $assertions = $this->assertions();

    if ($case['subject_label'] === 'haiku') {
        // Cheaper model gets a looser threshold.
        $assertions[] = Rubric::make(
            'Must at least get the polarity direction right.'
        )->minScore(0.6);
    }

    return $assertions;
}
```

Subjects can be anything `EvalSuite::subject()` accepts: class-string
FQCNs (resolved from the container), Agent instances, or closures.
Mix them freely inside a single `subjects()` map.

## Running

From the CLI:

```bash
php artisan evals:providers "App\\Evals\\SentimentMatrixSuite" \
    --persist \
    --provider-concurrency=3 \
    --concurrency=5 \
    --format=table
```

| Flag                      | Effect                                                                              |
| ------------------------- | ----------------------------------------------------------------------------------- |
| `--persist`               | Persist the comparison and its per-subject runs to the database.                    |
| `--commit-sha=<sha>`      | Attach a commit SHA to the persisted comparison (for CI drill-downs).               |
| `--concurrency=<n>`       | Inner parallelism: cases run in parallel within each subject. Default `1`.          |
| `--provider-concurrency=<n>` | Outer parallelism: subjects run in parallel. `0` (default) runs all at once.     |
| `--fake-judge=<spec>`     | Short-circuit the judge: `pass`, `fail`, or a JSON file path (dev / CI only).       |
| `--format=<table\|json>`  | Output format. `table` renders a matrix; `json` is CI-friendly.                     |

Programmatically:

```php
use App\Evals\SentimentMatrixSuite;
use Mosaiqo\Proofread\Runner\ComparisonRunner;

$suite = app(SentimentMatrixSuite::class);

/** @var \Mosaiqo\Proofread\Support\EvalComparison $comparison */
$comparison = app(ComparisonRunner::class)->run(
    $suite,
    providerConcurrency: 3,
    caseConcurrency: 5,
);

foreach ($comparison->subjectLabels() as $label) {
    $run = $comparison->runForSubject($label);
    printf("%s: %d/%d passed\n", $label, $run->passedCount(), $run->total());
}
```

The in-memory `EvalComparison` support object is the same shape the
dashboard renders — suitable for building custom reporters without
touching the database.

## Output formats

`--format=table` renders a matrix, one row per case, one column per
subject:

```
Comparison "sentiment-matrix" — 12 cases × 3 subjects

  case              | haiku      | sonnet     | opus
  ----------------- | ---------- | ---------- | ----------
  case 0 (positive) | PASS       | PASS       | PASS
  case 1 (mixed)    | FAIL       | PASS       | PASS
  case 2 (negative) | PASS       | PASS       | PASS
  ...
  ----------------- | ---------- | ---------- | ----------
  Pass rate         | 75.0%      | 100.0%     | 100.0%
  Cost              | $0.0042    | $0.0189    | $0.0671
  Avg latency       | 812ms      | 1543ms     | 2104ms

Overall: 33/36 passed, 3 failed, 18.42s total
```

`--format=json` emits a structured payload suitable for piping into a
CI artifact:

```json
{
  "name": "sentiment-matrix",
  "dataset": "sentiment",
  "subjects": ["haiku", "sonnet", "opus"],
  "total_cases": 12,
  "duration_ms": 18420.3,
  "passed": true,
  "runs": [
    {
      "subject_label": "haiku",
      "passed": false,
      "pass_rate": 0.75,
      "cost_usd": 0.0042,
      "avg_latency_ms": 812.4,
      "duration_ms": 9732.1,
      "total_cases": 12,
      "passed_cases": 9,
      "failed_cases": 3
    }
  ]
}
```

## Persisting comparisons

With `--persist`, the runner creates one `eval_comparisons` row plus
one `eval_runs` row per subject (distinguished by the `subject_label`
column). Each per-subject run holds its own `eval_results`, so drill-
down to a specific cell is a single join.

Query the persisted data:

```php
use Mosaiqo\Proofread\Models\EvalComparison;

$latest = EvalComparison::query()->latest()->first();

$best = $latest->bestByPassRate();
$cheap = $latest->cheapest();
$fast = $latest->fastest();

printf(
    "Best: %s (%d/%d) — Cheapest: %s (\$%s) — Fastest: %s (%sms)\n",
    $best->subject_label,
    $best->pass_count,
    $best->total_count,
    $cheap->subject_label,
    number_format((float) $cheap->total_cost_usd, 4),
    $fast->subject_label,
    $fast->duration_ms,
);
```

The three helpers are intentionally independent — Proofread does not
pick "the winner." You do, because the right answer depends on which
axis you're optimizing for today.

- `bestByPassRate()` ties go to the fastest run.
- `cheapest()` ignores runs with a `null` cost (i.e. models missing
  from the pricing table).
- `fastest()` is strict on `duration_ms`.

## Dashboard view

Two pages in the Proofread dashboard surface comparisons:

- `/evals/comparisons` — list of persisted comparisons. Each row shows
  subject pills with their individual pass rates.
- `/evals/comparisons/{id}` — matrix view, one row per case, one
  column per subject. Click any cell to open a drawer showing that
  case's assertion results for that subject. Winner cards at the top
  surface the best / cheapest / fastest subject.

## Exporting

```bash
php artisan evals:export <comparison_id> --format=md --output=./report.md
```

The exporter auto-detects whether the reference is a run ULID, a
commit SHA, or a comparison ID (you can also pin it via `--type=run`
or `--type=comparison`). Formats are `md` and `html`. The rendered
document includes the full matrix, per-subject summaries, and a
winners section based on the same helpers described above.

## MCP tool

For MCP-compatible editors, the package exposes
`run_provider_comparison` as a native MCP tool (see
`Mosaiqo\Proofread\Mcp\Tools\RunProviderComparisonTool`). The client
passes a suite FQCN and the same flags accepted by the CLI command;
the response is the same JSON payload.

## Parallelism cautions

The two concurrency knobs compound. Consider:

- `--provider-concurrency=3` runs three subjects concurrently.
- `--concurrency=5` runs five cases concurrently *within each subject*.

That is up to **15 simultaneous API calls** at any moment. For
same-provider subjects this easily trips per-account rate limits; for
cross-provider setups it can be fine. Two practical defaults:

- **Cross-provider.** `--provider-concurrency=0 --concurrency=5` — all
  providers at once, five cases in flight per provider. Each provider
  has its own rate-limit bucket.
- **Same provider.** `--provider-concurrency=1 --concurrency=5` —
  subjects sequentially, five cases in parallel. Keeps the in-flight
  total at five and prevents one subject starving another.

> **[warn]** Inner × outer concurrency compounds. `--concurrency=5
> --provider-concurrency=3` means up to 15 simultaneous API calls.
> Respect your provider's rate limits and your wallet's limits.

Judge calls from Rubric / Hallucination / Language assertions also
count against the judge's rate limit, which is usually a separate
bucket. Fake the judge with `--fake-judge=pass` in CI to isolate model
performance from judge noise when you only care about latency / cost
comparisons.

## See also

- [Assertions deep dive](/docs/guides/assertions-deep-dive) — picking
  the right assertions for a matrix.
- [Shadow evals](/docs/guides/shadow-evals) — the other production
  signal feeding into model-migration decisions.
- [Eval suites](/docs/eval-suites) — the single-subject base your
  `MultiSubjectEvalSuite` extends.

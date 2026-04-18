---
title: Persistence
section: Running
---

# Persistence

Persisting runs unlocks comparison across commits, dataset versioning, pass-
rate trends, and the `EvalRun` Eloquent model for querying historical data.

## Enabling persistence

Pass `--persist` to `evals:run`:

```bash
php artisan evals:run "App\\Evals\\SentimentSuite" --persist
```

Each run writes to four tables (created by the published migrations):

- `eval_datasets` — one row per dataset name.
- `eval_dataset_versions` — one row per unique content checksum.
- `eval_runs` — one row per suite execution.
- `eval_results` — one row per case within a run.

## The `EvalRun` Eloquent model

`Mosaiqo\Proofread\Models\EvalRun` exposes the persisted run:

```php
use Mosaiqo\Proofread\Models\EvalRun;

$run = EvalRun::latest()->first();

$run->passed;          // bool — overall pass
$run->pass_count;      // int — cases that passed
$run->total_count;     // int — total cases
$run->pass_rate;       // float — pass_count / total_count

$run->results();         // HasMany — per-case EvalResult rows
$run->dataset();         // BelongsTo — EvalDataset
$run->datasetVersion();  // BelongsTo — EvalDatasetVersion
$run->comparison();      // relation to its EvalComparison if one was computed
```

## Dataset versioning

Dataset versions are content-addressed: Proofread hashes each dataset's
cases and stores one `eval_dataset_versions` row per unique checksum.
Renaming a case or tweaking an input produces a new version automatically.

Inspect what changed between versions:

```bash
php artisan evals:dataset:diff sentiment
```

## Comparing runs

```bash
php artisan evals:compare <base> <head>
```

Both arguments accept:

- A numeric run id: `42`
- A commit SHA: `abc1234`
- The literal `latest`

Typical CI use: compare the PR's run against the main branch's most recent
run.

```bash
php artisan evals:compare latest abc1234
```

> **[info]** `latest` resolves to the most recent run for both
> `evals:compare` and `evals:dataset:diff`. Combine with `--commit-sha` on
> `evals:run --queue` to make PR-vs-main comparisons deterministic.

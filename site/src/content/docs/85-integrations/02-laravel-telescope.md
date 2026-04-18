---
title: "Laravel Telescope"
section: "Integrations"
---

# Laravel Telescope

Persisted eval runs appear in Telescope alongside queries, jobs, and
requests. No configuration beyond installing Telescope is required —
the watcher is registered conditionally at boot.

## Installation

```bash
composer require laravel/telescope --dev
```

Proofread detects Telescope via `class_exists(\Laravel\Telescope\Telescope::class)`
inside `ProofreadServiceProvider::packageBooted()`. When the class is
present, `EvalRunWatcher` is registered as a listener for the
`EvalRunPersisted` event. When it is absent, the integration is a
no-op.

## What gets recorded

Every `EvalRunPersisted` event — fired by `EvalPersister` after a run
is written to the database — triggers `EvalRunWatcher::handle()`,
which records a custom Telescope entry. Transient runs that never
persist do **not** appear in Telescope.

The entry's `name` attribute is the fully-qualified
`Mosaiqo\Proofread\Events\EvalRunPersisted` class.

## Entry content

Fields captured on each Telescope entry:

- `eval_run_id`, `dataset_name`, `suite_class`.
- `subject_type`, `subject_class`, `subject_label`.
- `passed`, `pass_count`, `fail_count`, `error_count`, `total_count`.
- `duration_ms`, `total_cost_usd`, `total_tokens_in`,
  `total_tokens_out`.
- `model`, `commit_sha`.

Tags attached to each entry:

- `proofread_eval` (always).
- `dataset:{name}` (always).
- `suite:{class}` (when the run has a suite class).
- `commit:{sha}` (when the run has a commit SHA).
- `status:passed` or `status:failed`.

## Filtering in Telescope UI

Navigate to `/telescope/events` and filter by tag to slice the feed:

- `dataset:sentiment-classification` — all runs of a single dataset.
- `status:failed` — triage regressions.
- `suite:App\Evals\MyRubricSuite` — a specific suite.
- `commit:abc123` — everything tied to a CI build.

## Telescope pause

The watcher respects `Telescope::isRecording()`. When Telescope is
globally paused (via `Telescope::stopRecording()` or the
`telescope:pause` command), no entries are written. Resuming
recording reactivates the watcher without any Proofread-side changes.

## Pruning

Telescope stores entries in your main database. Schedule its pruning
command to keep the table in check:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('telescope:prune --hours=48')->daily();
```

Proofread does not enforce its own retention for Telescope entries —
this is entirely Telescope's concern. Persisted `EvalRun` rows remain
regardless of Telescope pruning; see the
[persistence page](/docs/persistence) for details.

> **[warn]** Telescope stores entries in your main DB. Heavy eval
> traffic inflates `telescope_entries` quickly. Prune aggressively,
> and consider Telescope's `ignore` filters if only regressions are
> interesting.

## Relationship to the dashboard

Telescope gives you a flat event log. For cross-run comparisons,
regression detection, and cost breakdowns, use the
[Proofread dashboard](/docs/guides/dashboard) instead — it reads from
the same persisted tables but exposes run-oriented views.

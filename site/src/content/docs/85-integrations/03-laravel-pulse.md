---
title: "Laravel Pulse"
section: "Integrations"
---

# Laravel Pulse

Eval run metrics on the Pulse dashboard. Pass / fail counts, total
cost, and duration land alongside queries, jobs, and slow requests —
plus a publishable card that renders a Proofread-specific summary.

## Installation

```bash
composer require laravel/pulse
```

The `EvalPulseRecorder` listener is registered conditionally in
`ProofreadServiceProvider::packageBooted()` when both
`class_exists(\Laravel\Pulse\Pulse::class)` is true **and** the
container has `Pulse` bound. Partial installs (package present,
service provider disabled) cleanly skip the recorder.

## What gets recorded

Every `EvalRunPersisted` event fans out into three Pulse entries:

- `proofread_eval` — a `count()` aggregate keyed by
  `{dataset_name}::passed` or `{dataset_name}::failed`.
- `proofread_eval_duration` — `avg()` and `max()` aggregates keyed
  by `dataset_name`, value in integer milliseconds.
- `proofread_eval_cost` — `sum()` aggregate keyed by `dataset_name`,
  value in integer **micro-dollars** (see caveat below). Only
  recorded when the run has a non-null `total_cost_usd`.

Each entry's timestamp is taken from the run's `created_at`, so
back-dated persistence (via replay tooling, for instance) places the
metrics in the correct historical window.

## Publishable Pulse card

Publish the card stub:

```bash
php artisan vendor:publish --tag=proofread-pulse
```

It lands at
`resources/views/vendor/pulse/cards/proofread.blade.php`. The card
shows the 24-hour pass rate, total cost, average duration, and the
five most recent runs.

### Adding the card to your dashboard

Edit `resources/views/vendor/pulse/dashboard.blade.php` and include
the card:

```blade
<x-pulse>
    <livewire:pulse.servers cols="full" />
    <livewire:pulse.usage cols="4" rows="2" />

    @include('vendor.pulse.cards.proofread', ['cols' => 4, 'rows' => 2])
</x-pulse>
```

> **[info]** The card queries the `EvalRun` model directly — it does
> not depend on the Pulse recorder. Even if you skip the recorder
> integration entirely, the card still renders stats from persisted
> runs.

## Cost caveat: micro-dollars

Pulse's `value` column is `?int`. Proofread stores cost as
`cost_usd * 1_000_000` (integer micro-dollars) so sub-cent amounts
are preserved. Reconstruct dollars in custom queries:

```sql
SELECT SUM(value) / 1000000.0 AS total_cost
FROM pulse_entries
WHERE type = 'proofread_eval_cost'
  AND timestamp >= UNIX_TIMESTAMP(NOW() - INTERVAL 1 DAY);
```

If you need a Livewire card of your own, the same conversion applies
when reading aggregates via `Pulse::aggregate()`.

## Aggregation windows

Pulse's default windows (`1_hour`, `6_hours`, `24_hours`, `7_days`)
apply. Example:

```php
use Laravel\Pulse\Facades\Pulse;

$aggregates = Pulse::aggregate(
    'proofread_eval_cost',
    ['sum'],
    24 * 60 * 60,
);
```

Remember to divide by `1_000_000` for dollar values.

## Relationship to cost simulation

Pulse shows what you've already spent. For *projected* spend under
different dataset sizes or models, use the
[cost simulation guide](/docs/guides/cost-simulation) instead.

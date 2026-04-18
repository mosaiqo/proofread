---
title: Cost simulation
section: Guides
---

# Cost simulation

"If I migrated from Sonnet to Haiku, what would that cost me?" is one
of the easier decisions to estimate and one of the hardest to estimate
well. Back-of-envelope math tends to use averaged token counts, ignore
cache-read pricing, and rely on the pricing page you happen to have
open. Cost simulation replays the shadow captures you already have
against alternative models in the configured pricing table, then hands
you a side-by-side projection grounded in the traffic shape you
actually serve.

## The cost question

Token usage is not uniform. Long-context interactions, caching-heavy
flows, and reasoning-model tokens each weight the total differently,
and averaging hides those weights. Proofread's shadow pipeline already
stores per-capture token counts and the model that served each
request; the simulator is the component that turns those rows into a
what-if report.

## Running the command

```bash
php artisan evals:cost-simulate "App\\Agents\\SupportAgent" \
    --days=30 \
    --format=table
```

Flags:

| Flag       | Default | Purpose                                                                                     |
| ---------- | ------- | ------------------------------------------------------------------------------------------- |
| `--days`   | `30`    | Window size, in days, counting back from now.                                               |
| `--model`  | empty   | Repeatable (or comma-separated) list of alternative models. Empty means "everything in the pricing table except current". |
| `--format` | `table` | `table` or `json`.                                                                          |

Examples:

```bash
# Compare against two specific alternatives.
php artisan evals:cost-simulate "App\\Agents\\SupportAgent" \
    --model=claude-haiku-4-5 \
    --model=gpt-4o-mini

# Comma-separated form also works.
php artisan evals:cost-simulate "App\\Agents\\SupportAgent" \
    --model=claude-haiku-4-5,gpt-4o-mini
```

## How the current model is determined

The simulator does not ask you which model is "current" — it derives
it from the captures. Every `ShadowCapture::model_used` in the window
is counted and the mode wins. Ties break by the most recent
`captured_at`, which matches the intuition that if a migration is
in-flight, the newer model is the one you're reasoning about going
forward.

Captures with no recorded model contribute nothing to the tally.

## Output

Table format (abridged):

```
Cost simulation for App\Agents\SupportAgent
Window: 2026-03-18 to 2026-04-17 (30 days)
Captures: 1,247

Current: claude-sonnet-4-6
  Total cost:    $4.5000
  Per capture:   $0.0036
  Covered:       1,247 / 1,247 captures

Alternatives:
  Model             | Total      | Delta         | Per capture  | Coverage
  ----------------- | ---------- | ------------- | ------------ | ----------
  claude-haiku-4-5  | $1.3500    | -$3.1500      | $0.0011      | 1247/1247
  gpt-4o-mini       | $0.6200    | -$3.8800      | $0.0005      | 1247/1247
  gpt-4o            | $3.7500    | -$0.7500      | $0.0030      | 1247/1247
  claude-opus-4-6   | $22.5000   | +$18.0000     | $0.0180      | 1247/1247

Cheapest: gpt-4o-mini - save $3.8800 (86.2% less)
```

Reading the columns:

- **Total** and **Per capture** are in USD, formatted to four decimal
  places. Per-capture is Total divided by the covered-capture count
  (so `null` token rows don't drag the average toward zero).
- **Delta** is signed: `-` is savings against the current model, `+`
  is more expensive. The column is sorted ascending on total cost,
  so cheapest-first.
- **Coverage** is `covered / total` captures. A projection skips a
  capture when both `tokens_in` and `tokens_out` are null, or when
  the pricing table has no entry for that model.
- The **Cheapest** line only appears when some alternative beats
  current total cost.

The JSON format exposes everything the table does plus the raw
`skipped_captures` counts and a structured `cheapest_alternative`
block with savings percentage — ideal for pipelines that gate
migrations on a threshold.

## Decision-making

Cost on its own does not justify a migration. Every simulation should
be paired with:

- **Quality parity.** Run a multi-provider comparison on the same
  dataset to confirm the cheaper model does not collapse your pass
  rate.
- **Prompt fit.** Lint the prompt against the target model's
  conventions. A prompt tuned for Sonnet may need tightening before
  Haiku can hit the same marks.

A sensible cadence for most teams is a monthly simulation run.
Traffic shape drifts, pricing changes, and models get deprecated —
the answer to "should we switch?" is not static.

## Extending the pricing table

The default pricing table in `config/proofread.php` ships approximate
per-million-token rates for the Anthropic, OpenAI, and a handful of
other model families. Every entry is a snapshot and will drift from
live pricing. Override or extend the table in your own config:

```php
return [
    // ...
    'pricing' => [
        'models' => [
            'my-custom-model' => [
                'input_per_1m' => 5.00,
                'output_per_1m' => 20.00,
                'cache_read_per_1m' => 0.50,
                'cache_write_per_1m' => 6.25,
                'reasoning_per_1m' => 20.00,
            ],
        ],
    ],
];
```

`cache_read_per_1m`, `cache_write_per_1m`, and `reasoning_per_1m`
are optional — omit them for models that don't price those channels
separately, and the simulator will treat the missing categories as
zero rather than fail.

Any model absent from the pricing table simply reports a null cost
for each capture, which increments the skipped count on that
projection. Missing pricing never crashes the command.

## Limitations

- **Requires shadow captures.** The simulator runs entirely off
  persisted capture rows. Without the shadow pipeline, there is
  nothing to project against.
- **Token counts are held constant.** The simulation assumes the new
  model would receive the same `tokens_in` / `tokens_out` shape as
  the current. For drop-in replacements this is fair; for migrations
  that change tooling or prompt structure it is optimistic.
- **Pricing is static per run.** For long windows that straddle a
  price change, the current pricing is applied uniformly across all
  captures. If you need historical accuracy for an audit, shorten
  the window or split runs around the price-change date.
- **Null-token captures are invisible.** Captures missing both token
  counts are skipped on every projection — they do not bias the
  totals, but they also do not contribute.

## Related

- [Shadow evals](/docs/90-guides/02-shadow-evals) — the source of
  every capture the simulator reads.
- [Multi-provider comparison](/docs/90-guides/03-multi-provider) —
  confirms the quality side of a migration the simulator is tempting
  you toward on cost.
- [Dataset coverage analysis](/docs/90-guides/06-dataset-coverage) —
  before migrating, check the dataset represents the traffic the
  simulation is projected on.

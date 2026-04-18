---
title: Shadow evals
section: Guides
---

# Shadow evals

Static datasets catch the bugs you know about. Production traffic
catches the ones you don't. Shadow evals capture a sampled slice of
real agent invocations, sanitize them, evaluate them asynchronously
against assertions you register per agent, and alert when the rolling
pass rate drops. This is Proofread's answer to the "my evals are green
but the product is degrading" problem.

## Conceptual overview

Two data stores back the feature:

- **`shadow_captures`** — one row per sampled invocation. Holds the
  sanitized input, the output, token counts, cost, latency, model, and
  the timestamp at which the capture happened. These are written by a
  queued job, not on the request path.
- **`shadow_evals`** — one row per `(capture, assertion)` pair produced
  by the shadow evaluator. Holds the verdict, score, reason, metadata,
  and the assertion name.

Captures are cheap to keep around. Evals are where the cost lives —
they run the same assertions you run in CI, including any judge or
embedding calls. The evaluator is intentionally a separate process: it
lets you re-evaluate historical captures when you add a new assertion
(`--force`), tune thresholds, or switch judges without re-running the
model.

Compared with static-dataset evals, shadow evals give you:

- **Drift detection.** Real traffic covers the edge cases your
  fixtures miss.
- **Regression triage.** When alerts fire, the captured inputs become
  the bug report.
- **Dataset mining.** Promote representative captures into a curated
  dataset and regression-test them forever after.

## Enabling shadow capture

Shadow capture is opt-in. Configure it in `config/proofread.php`:

```php
'shadow' => [
    'enabled' => env('PROOFREAD_SHADOW_ENABLED', false),
    'sample_rate' => (float) env('PROOFREAD_SHADOW_SAMPLE_RATE', 0.1),
    'agents' => [
        App\Agents\SupportAgent::class => ['sample_rate' => 0.25],
    ],
    'queue' => env('PROOFREAD_SHADOW_QUEUE', 'default'),
    'sanitize' => [
        'pii_keys' => ['email', 'phone', 'ssn', 'credit_card', 'password', 'api_key', 'token'],
        'redact_patterns' => [
            '/\b(?:\d[ -]*?){13,19}\b/' => '[CARD]',
            '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/' => '[EMAIL]',
        ],
        'max_input_length' => 2000,
        'max_output_length' => 5000,
        'redacted_placeholder' => '[REDACTED]',
    ],
    'alerts' => [/* see below */],
],
```

The per-agent `sample_rate` overrides the global default. Set it to a
higher rate for agents you care about most, and keep the global rate
conservative.

Attach the middleware to the agents you want captured:

```php
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;
use Mosaiqo\Proofread\Shadow\EvalShadowMiddleware;

final class SupportAgent implements Agent, HasMiddleware
{
    use Promptable;

    public function middleware(): array
    {
        return [EvalShadowMiddleware::class];
    }
}
```

> **[info]** The middleware is non-blocking. It lets the response reach
> the caller first, then asynchronously dispatches
> `PersistShadowCaptureJob`. Sampling and queueing failures never
> impact the product.

What the middleware captures per sampled request:

- `raw_input` — the prompt and attachments, pre-sanitization.
- `output` — the agent's textual response.
- `tokens_in`, `tokens_out` — usage from the response.
- `model`, `latency_ms`, `captured_at`.

Sanitization happens inside the job, not in the middleware — see the
next section.

## Sanitization

`PiiSanitizer` applies three passes in order:

1. **Key blacklist.** Recursively walks arrays/objects and replaces any
   value whose key matches `pii_keys` with the redacted placeholder.
   Good for structured prompts (`['user' => ['email' => '…']]`) that
   pass PII by convention.
2. **Pattern redaction.** Regex replacements from `redact_patterns`
   applied to every string value. The shipped defaults cover emails and
   card-shaped digit runs.
3. **Truncation.** Applies `max_input_length` to prompt strings and
   `max_output_length` to the output. Truncated content is suffixed
   with `…`.

Because sanitization runs in the queued job, the request path is
unaffected by regex cost. If the job fails, nothing persists; retries
go through Laravel's usual queue retry policy.

> **[warn]** Shadow sanitization is a best-effort net, not a compliance
> boundary. Regex patterns miss novel PII formats and blacklists miss
> novel keys. Review the sanitization rules every time your prompts
> change shape, and never route captures through an unsanitized
> pipeline for a regulated workload.

## Evaluating captures

Captures carry no assertion metadata of their own. You register
assertions per agent class at boot time — typically in a service
provider:

```php
use App\Agents\SupportAgent;
use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Assertions\LatencyLimit;
use Mosaiqo\Proofread\Assertions\PiiLeakageAssertion;
use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Proofread;

public function boot(): void
{
    Proofread::registerShadowAssertions(SupportAgent::class, fn () => [
        Rubric::make('The response must be polite, concise, and answer the user.')
            ->minScore(0.8),
        CostLimit::under(0.05),
        LatencyLimit::under(4_000),
        PiiLeakageAssertion::make(),
        ContainsAssertion::make('support@', caseSensitive: false),
    ]);
}
```

The resolver is a closure, not an array — so you can build assertions
lazily (pulling patterns from the database, swapping judges by
environment, etc.) without running that code at every request.

Run the evaluator:

```bash
php artisan shadow:evaluate \
    --agent="App\\Agents\\SupportAgent" \
    --since=1h \
    --batch=500
```

Flags:

| Flag        | Effect                                                                              |
| ----------- | ----------------------------------------------------------------------------------- |
| `--agent`   | Filter by agent FQCN. Omit to evaluate every registered agent's captures.           |
| `--since`   | Relative window (`1h`, `24h`, `7d`). Accepted units are documented in the parser.   |
| `--batch`   | Max captures processed per invocation. Defaults to `100`.                           |
| `--force`   | Re-evaluate captures that already have a `ShadowEval`. Use when assertions changed. |
| `--dry-run` | Wrap the evaluation in a transaction and roll back. Nothing persists.               |

Schedule it somewhere:

```php
// app/Console/Kernel.php
$schedule->command('shadow:evaluate')
    ->everyTenMinutes()
    ->withoutOverlapping();
```

At the end of each run the command prints a compact summary:

```
Summary:
  Processed:  312
  Skipped:    4 (no assertions configured)
  Evals:      924 created
  Pass rate:  94.2% (870 passed, 54 failed)
  Judge cost: $0.1284
  Duration:   11284ms
```

> **[info]** Captures with no registered assertions are skipped rather
> than failed. If you expected a number greater than zero and see
> `Skipped`, check that your service provider actually registered the
> agent.

## Alerts

Shadow alerts watch the rolling pass rate per agent and dispatch a
notification when it falls below the configured threshold.

```php
'alerts' => [
    'enabled' => env('PROOFREAD_SHADOW_ALERTS_ENABLED', true),
    'pass_rate_threshold' => (float) env('PROOFREAD_SHADOW_ALERT_THRESHOLD', 0.85),
    'window' => env('PROOFREAD_SHADOW_ALERT_WINDOW', '1h'),
    'min_sample_size' => (int) env('PROOFREAD_SHADOW_ALERT_MIN_SAMPLES', 10),
    'dedup_window' => env('PROOFREAD_SHADOW_ALERT_DEDUP', '1h'),
    'channels' => ['mail'],
    'mail' => ['to' => env('PROOFREAD_ALERT_MAIL_TO')],
],
```

- `pass_rate_threshold` — fail below this fraction of passing evals.
- `window` — rolling window over which the pass rate is computed.
- `min_sample_size` — minimum number of evals required in the window
  before an alert can fire. Prevents a single failure from paging.
- `dedup_window` — after an alert fires for an agent, the command will
  not fire again for that agent during this window. Cache-backed.

Run the command on a schedule:

```bash
php artisan shadow:alert
```

```php
// app/Console/Kernel.php
$schedule->command('shadow:alert')->hourly();
```

`--agent=<Class>` scopes the check to a single agent.  `--dry-run`
prints what would fire and skips both the notification and the dedup
marker.

The notification (`ShadowPassRateDroppedNotification`) carries a
`ShadowAlert` DTO with:

- `agentClass`
- `passRate`
- `threshold`
- `passedCount`
- `sampleSize`

> **[info]** Only the `mail` channel ships by default. Slack, Discord,
> and generic webhooks are covered separately by
> `proofread.webhooks` — or write a bespoke channel that reads the DTO
> and pushes wherever you need.

## Dashboard and promote-to-dataset

The Proofread dashboard at `/evals/shadow` lists captures, their
evaluation status, and the rolling pass rate per agent. Clicking a row
opens a drawer with:

- Sanitized input and output.
- Assertion verdicts with reasons and metadata.
- The per-capture token / cost / latency numbers.

A "Promote to dataset" action in the drawer generates a ready-to-paste
PHP snippet for your suite's `dataset()` — preserving the sanitized
input and the observed output as the baseline expected value. Paste it
into the suite, review, commit. You now have a regression test against
a real production scenario.

## Production checklist

Before enabling shadow capture in a real environment:

- **Sample rate.** Start at `0.01`–`0.05`. You can always raise it.
  Per-agent overrides let you hand-pick the agents worth sampling
  harder.
- **Queue.** Point `proofread.shadow.queue` at a dedicated low-priority
  queue (e.g. `evals-low`). Shadow jobs should never delay real work
  when the queue is saturated.
- **Retention.** Add a scheduled prune to drop old captures and evals.
  A simple `DELETE FROM shadow_captures WHERE captured_at < ?` keeps
  storage bounded; a join purge removes orphan evals.
- **Privacy review.** Run a sample of recent captures past whoever
  owns your data-handling policy. Expand `pii_keys` and
  `redact_patterns` until that person signs off.
- **Judge cost.** Rubric assertions in shadow are the single biggest
  spend. Either judge a subset (e.g. every Nth capture in a custom
  assertion) or pick a cheaper judge model in
  `proofread.judge.default_model`.
- **Alerting plumbing.** Verify `mail.to` is a monitored address, or
  swap in a Slack webhook. Alerts on an abandoned inbox are worse
  than no alerts.
- **Monitoring.** Watch queue-job failure rates for
  `PersistShadowCaptureJob` and the dedup cache hit rate. Both are
  proxies for "shadow is healthy."

## See also

- [Assertions deep dive](/docs/guides/assertions-deep-dive) — every
  assertion you can register against a capture.
- [Persistence](/docs/persistence) — the wider data model.
- [Multi-provider comparison](/docs/guides/multi-provider) — for
  model-migration decisions informed by shadow data.

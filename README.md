# Proofread

> The only eval package native to the official Laravel AI stack.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mosaiqo/proofread.svg?style=flat-square)](https://packagist.org/packages/mosaiqo/proofread)
[![Tests](https://img.shields.io/github/actions/workflow/status/mosaiqo/proofread/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/mosaiqo/proofread/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/mosaiqo/proofread.svg?style=flat-square)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.4-blue.svg?style=flat-square)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/laravel-13.x-red.svg?style=flat-square)](https://laravel.com)

> **Status:** Early development — pre-1.0, API unstable.

## What it does

Modern Laravel apps increasingly ship AI agents, prompts, and MCP tools straight
to production. The official `laravel/ai` SDK makes building them easy, but the
feedback loop for *evaluating* them has lived outside the framework in
language-agnostic tools like Promptfoo. Proofread brings that loop home: a
Laravel-native way to measure whether your agents actually do what you think
they do, from Pest, from CI, and from production traffic.

Three things make Proofread different:

- **Agent classes are first-class eval subjects.** Point an eval at an FQCN,
  an instance, or a callable — `SubjectResolver` normalizes all of them, and
  `laravel/ai` fakes plug in automatically.
- **Pest-native expectations.** Write evals as `expect($agent)->toPassEval(...)`
  instead of learning a new YAML DSL. Rubrics, JSON schemas, cost ceilings, and
  golden snapshots are all `expect()` extensions.
- **Shadow evals in production.** Capture real traffic via middleware,
  evaluate it asynchronously against registered assertions, alert on
  pass-rate drops, and promote failing captures into regression datasets.

## Installation

```bash
composer require mosaiqo/proofread
```

Requires PHP 8.4 and Laravel 13.x.

Optional MCP integration (expose eval tools to Claude Code and other MCP
clients):

```bash
composer require laravel/mcp
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=proofread-config
php artisan vendor:publish --tag=proofread-migrations
php artisan migrate
```

## Quick start

### 1. Define an agent with `laravel/ai`

```php
namespace App\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class SentimentAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
            You are a sentiment classifier.
            Classify the user message as exactly one of: positive, negative, neutral.
            Respond with a single lowercase word. No punctuation, no explanation.
            PROMPT;
    }
}
```

### 2. Write a Pest eval

```php
use App\Agents\SentimentAgent;
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Support\Dataset;

it('classifies sentiment reliably and cheaply', function (): void {
    $dataset = Dataset::make('sentiment', [
        ['input' => 'I love this product!', 'expected' => 'positive'],
        ['input' => 'This is terrible.', 'expected' => 'negative'],
        ['input' => 'It works as described.', 'expected' => 'neutral'],
    ]);

    expect(SentimentAgent::class)->toPassEval($dataset, [
        RegexAssertion::make('/^(positive|negative|neutral)$/'),
        Rubric::make('response is a single lowercase sentiment label'),
        CostLimit::under(0.01),
    ]);
});
```

### 3. Or wrap it in an `EvalSuite` and run from Artisan

```php
namespace App\Evals;

use App\Agents\SentimentAgent;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class SentimentSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('sentiment', [
            ['input' => 'I love this product!', 'expected' => 'positive'],
            ['input' => 'This is terrible.', 'expected' => 'negative'],
        ]);
    }

    public function subject(): mixed
    {
        return SentimentAgent::class;
    }

    public function assertions(): array
    {
        return [
            RegexAssertion::make('/^(positive|negative|neutral)$/'),
        ];
    }
}
```

```bash
php artisan evals:run "App\\Evals\\SentimentSuite"
```

## Core concepts

### EvalSuite

A suite bundles a dataset, a subject, and a list of assertions. Extend
`Mosaiqo\Proofread\Suite\EvalSuite` and implement three methods:

```php
abstract public function dataset(): Dataset;
abstract public function subject(): mixed;
abstract public function assertions(): array;
```

See `src/Suite/EvalSuite.php` for the full contract.

Suites can optionally override `setUp()` and `tearDown()` for
database-dependent setup, and `assertionsFor(array $case)` to vary
assertions per case based on metadata. Both `toPassSuite()` in Pest
and the `evals:run` Artisan command drive the full lifecycle
automatically.

### Subjects

Three shapes are accepted:

- **Callable** — a closure or any `callable`, receives `(mixed $input, array $case)`.
- **Agent FQCN** — a class-string implementing `Laravel\Ai\Contracts\Agent`.
  Resolved from the container on each case.
- **Agent instance** — useful when you want to pre-configure the agent.

`Mosaiqo\Proofread\Runner\SubjectResolver` normalizes all three into a uniform
closure and captures token usage, model, provider, latency, and derived cost
into assertion metadata.

### Assertions

| Assertion | Kind | Example |
|---|---|---|
| `ContainsAssertion` | deterministic | `ContainsAssertion::make('positive')` |
| `RegexAssertion` | deterministic | `RegexAssertion::make('/^\d+$/')` |
| `LengthAssertion` | deterministic | `LengthAssertion::between(5, 200)` |
| `CountAssertion`     | deterministic | `CountAssertion::between(1, 10)`                       |
| `JsonSchemaAssertion` | deterministic | `JsonSchemaAssertion::fromAgent(MyAgent::class)` |
| `TokenBudget` | operational | `TokenBudget::maxTotal(1000)` |
| `CostLimit` | operational | `CostLimit::under(0.01)` |
| `LatencyLimit` | operational | `LatencyLimit::under(3000)` |
| `Rubric` | semantic (LLM-as-judge) | `Rubric::make('polite and concise')` |
| `Similar` | semantic (embeddings) | `Similar::to('reference text')->minScore(0.8)` |
| `Trajectory` | trajectory | `Trajectory::callsTool('search')` |
| `GoldenSnapshot` | snapshot | `GoldenSnapshot::fromContext()` |

All assertions live under `Mosaiqo\Proofread\Assertions\`. Each one returns an
`AssertionResult` with a `passed` bool, a human-readable `reason`, an optional
numeric `score`, and arbitrary `metadata`.

### Pest expectations

| Expectation | Subject | Usage |
|---|---|---|
| `toPassAssertion` | any output | `expect($output)->toPassAssertion(ContainsAssertion::make('x'))` |
| `toPassEval` | callable / Agent | `expect($agent)->toPassEval($dataset, $assertions)` |
| `toPassSuite`            | EvalSuite         | `expect($suite)->toPassSuite()`                       |
| `toPassRubric` | string output | `expect($output)->toPassRubric('polite tone')` |
| `toMatchSchema` | JSON output | `expect($json)->toMatchSchema($schemaArray)` |
| `toCostUnder` | `EvalRun` | `expect($run)->toCostUnder(0.05)` |
| `toMatchGoldenSnapshot` | any output | `expect($output)->toMatchGoldenSnapshot()` |

Expectations are loaded via `Mosaiqo\Proofread\Testing\expectations.php`; wire
it up from your `tests/Pest.php`:

```php
require_once __DIR__.'/../vendor/mosaiqo/proofread/src/Testing/expectations.php';
```

A bundled PHPStan extension teaches static analysis about these dynamic
expectations — no stub files to maintain.

### Artisan commands

| Command | Purpose |
|---|---|
| `evals:run {suites*}` | Run one or more `EvalSuite` classes. Supports `--persist`, `--fail-fast`, `--filter`, `--junit`, `--queue`, `--commit-sha`, and `--fake-judge` (pass, fail, or JSON path). |
| `evals:compare {base} {head}` | Structured diff between two persisted runs |
| `evals:cluster` | Cluster failures by embedding similarity |
| `shadow:evaluate` | Evaluate captured shadow traffic against registered assertions |
| `shadow:alert` | Check pass-rate alerts against thresholds |
| `dataset:generate` | Generate synthetic cases from a schema via an LLM |

Each command supports `--help` for the full flag list.

## Shadow evals

Shadow evals let you measure agent quality on real production traffic without
affecting the user-facing response path.

The flow is:

1. Apply `Mosaiqo\Proofread\Shadow\EvalShadowMiddleware` to routes that call
   your agent. It samples a configurable fraction of requests, sanitizes PII,
   and persists a `ShadowCapture` row.
2. A queued job (or `shadow:evaluate` on a schedule) runs the registered
   assertions for that agent over new captures and records a `ShadowEval`.
3. `shadow:alert` checks the pass rate over a sliding window and dispatches
   a notification (mail, Slack webhook, etc.) when it drops below the
   configured threshold.
4. From the dashboard you can promote any failing capture straight into a
   regression dataset.

Register the assertions you want to evaluate per agent in a service provider:

```php
use App\Agents\SentimentAgent;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Shadow\ShadowAssertionsRegistry;

public function boot(ShadowAssertionsRegistry $registry): void
{
    $registry->register(SentimentAgent::class, [
        RegexAssertion::make('/^(positive|negative|neutral)$/'),
    ]);
}
```

And enable shadow capture in `config/proofread.php`:

```php
'shadow' => [
    'enabled' => true,
    'sample_rate' => 0.1,
    'queue' => 'default',
    'alerts' => [
        'enabled' => true,
        'pass_rate_threshold' => 0.85,
        'window' => '1h',
        'channels' => ['mail', 'slack'],
    ],
],
```

See `src/Shadow/` for the full surface.

## Dashboard

A Livewire-powered dashboard ships with the package at `/evals` (configurable).
Routes:

- `/evals/overview` — home with trend chart and recent regressions
- `/evals/runs` — run history with filters and stats
- `/evals/runs/{ulid}` — per-case drill-down
- `/evals/datasets` — dataset explorer with sparklines
- `/evals/compare` — side-by-side diff between two runs
- `/evals/costs` — cost breakdown by model and dataset
- `/evals/shadow` — captures, evals, and promote-to-dataset workflow

Access is controlled by the `viewEvals` Gate. The default definition allows
the `local` environment only; override it in your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewEvals', fn ($user) => $user?->isAdmin() === true);
}
```

## Pricing table

Proofread ships approximate pricing for common models (Claude 4.x, GPT-4o,
o1 series, Gemini 1.5, OpenAI embeddings). Pricing covers input, output,
cache reads, cache writes, and reasoning tokens. `CostLimit`, the dashboard
cost view, and shadow capture cost tracking all work out of the box for
supported models.

Override or extend the table in `config/proofread.php`:

```php
'pricing' => [
    'models' => [
        'claude-sonnet-4-6' => [
            'input_per_1m' => 3.00,
            'output_per_1m' => 15.00,
            'cache_read_per_1m' => 0.30,
            'cache_write_per_1m' => 3.75,
        ],
        // ...
    ],
],
```

Any model absent from the table reports a null cost — `CostLimit` fails
closed on missing data rather than silently passing.

## MCP integration

When `laravel/mcp` is installed, Proofread exposes three tools:

- `list_eval_suites` — list registered `EvalSuite` classes
- `run_eval_suite` — run a suite and return the structured result
- `get_eval_run_diff` — diff two persisted runs by ULID

Register them from your own `Server` subclass:

```php
use Laravel\Mcp\Server;
use Mosaiqo\Proofread\Mcp\McpIntegration;

class ProofreadMcpServer extends Server
{
    protected array $tools = McpIntegration::tools();
}
```

And declare which suites are discoverable via the tool in
`config/proofread.php`:

```php
'mcp' => [
    'suites' => [
        \App\Evals\SentimentSuite::class,
    ],
],
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=proofread-config
```

Key sections (see `config/proofread.php` for the annotated full file):

- `judge` — default model and retries for LLM-as-judge (`Rubric`)
- `similarity` — default embedding model for `Similar` and clustering
- `pricing.models` — per-model token rates (input, output, cache, reasoning)
- `snapshots` — `GoldenSnapshot` storage path and update mode
- `dashboard` — enable/disable, path, middleware stack
- `shadow` — capture sampling, PII sanitization, alerts, queue connection
- `queue` — async eval execution connection and queue
- `webhooks` — regression alerts (Slack, Discord, generic JSON)
- `mcp` — suites exposed through MCP tools

## Testing the package itself

```bash
composer test       # Pest v4 against Testbench
composer analyse    # PHPStan
composer format     # Pint
```

All three must pass on every commit to `main`.

## Contributing

Contributions are welcome. The development workflow is:

- **TDD always.** Red, green, refactor.
- **Work directly on `main`** while the package is pre-v1. Every commit must
  be green (`composer test`, `composer format`, `composer analyse`).
- Commits in English, imperative mood (`Add X`, not `Added X`).

See [CLAUDE.md](CLAUDE.md) for the full set of conventions.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

## Credits

- [Boudy de Geer](https://github.com/boudy) / [Mosaiqo](https://mosaiqo.com)

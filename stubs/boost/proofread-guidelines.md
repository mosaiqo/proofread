# Proofread guidelines

Proofread is a Laravel-native eval package for the official `laravel/ai`
stack. Use it to evaluate agents, prompts, and MCP tools from Pest,
from CI, and from production traffic. These guidelines describe how
to write idiomatic suites, pick the right assertion, and drive the
CLI. Follow them when generating or refactoring Proofread code.

## When to use what

- **Pest expectations** (`toPassEval`, `toPassSuite`) — for ad-hoc
  checks inside regular feature or unit tests. They return an
  `EvalRun` so callers can chain cost or trajectory expectations.
- **`EvalSuite` classes** — for reusable evaluations with multiple
  cases, stable dataset naming, and persistence across runs. One
  suite per behavior, stored under `app/Evals/`.
- **Artisan commands** — for CI and local ops. `evals:run` to
  execute, `evals:compare` to diff persisted runs, `evals:benchmark`
  for stability analysis, `evals:cluster` for failure grouping.

## Writing an EvalSuite

Name classes in PascalCase with the `Suite` suffix; name the
dataset in kebab-case matching the class name:

```php
namespace App\Evals;

use App\Agents\SentimentAgent;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class SentimentClassificationSuite extends EvalSuite
{
    public function name(): string
    {
        return 'sentiment-classification';
    }

    public function dataset(): Dataset
    {
        return Dataset::make('sentiment-classification', [
            ['input' => 'I love this!', 'expected' => 'positive'],
            ['input' => 'Terrible experience.', 'expected' => 'negative'],
            ['input' => 'It works.', 'expected' => 'neutral'],
        ]);
    }

    public function subject(): string
    {
        return SentimentAgent::class;
    }

    public function assertions(): array
    {
        return [
            RegexAssertion::make('/^(positive|negative|neutral)$/'),
            Rubric::make('Response is a single lowercase word'),
        ];
    }
}
```

Cases are associative arrays with `input` (required), `expected`
(optional, for comparison assertions), and `meta` (optional, for
per-case customization). `subject()` may return a class-string
FQCN, an Agent instance, or a callable; `SubjectResolver`
normalizes all three.

Scaffold a suite with `php artisan proofread:make-suite Name`
and a dataset with `php artisan proofread:make-dataset Name`.

## Choosing assertions

Group assertions by the guarantee they provide.

**Deterministic.** No LLM calls. Use when the expected shape or
content is known exactly.

- `ContainsAssertion::make($needle)` — substring match.
- `RegexAssertion::make('/pattern/')` — regex match.
- `LengthAssertion::under($max)` / `::atLeast($min)` — character
  count guardrails.
- `JsonSchemaAssertion::conformsTo($schema)` — validate against a
  JSON schema.
- `CountAssertion::equalsExpected()` — output has same element
  count as `expected`.

**Operational.** Performance and budget guardrails.

- `TokenBudget::under($max)` — cap on input+output tokens.
- `CostLimit::under($usd)` — cap on per-case cost.
- `LatencyLimit::under($ms)` — cap on duration.

**Semantic.** LLM-as-judge; set `proofread.judge.default_model`.

- `Rubric::make($criteria)` — score the output against a rubric;
  chain `->minScore(0.8)` and `->using($model)`.
- `Similar::to($reference)` — cosine similarity of embeddings.
- `HallucinationAssertion::against($groundTruth)` — detect unsupported
  claims.
- `LanguageAssertion::matches($code)` — language detection.

**Structured.** Validate structured output conformance.

- `StructuredOutputAssertion::conformsTo($agentClass)` — validate
  against the schema declared by an Agent implementing
  `HasStructuredOutput`.

**Safety.** Detect unwanted output content.

- `PiiLeakageAssertion::make()` — flag emails, credit cards, custom
  patterns. Deterministic.

**Trajectory.** Validate agent reasoning steps.

- `Trajectory::stepCountUnder($max)` — cap on steps.
- `Trajectory::usesTool($name)` — require a specific tool call.

**Snapshot.** Compare against previously-approved output.

- `GoldenSnapshot::matches($key)` — file-based approval testing.
  Update with `PROOFREAD_UPDATE_SNAPSHOTS=1`.

## Per-case assertion overrides

Use `assertionsFor($case)` when a subset of cases needs extra
checks:

```php
public function assertionsFor(array $case): array
{
    $base = $this->assertions();

    if (($case['meta']['strict'] ?? false) === true) {
        $base[] = LengthAssertion::under(20);
    }

    return $base;
}
```

The CLI `evals:run` honors these overrides; suites that rely on
them should declare that via the `(per-case may vary)` header.

## Testing patterns

**Faking the judge.** Tests should never hit a real LLM. Use
`JudgeAgent::fake($score, $reason)` or the CLI flag
`--fake-judge=pass|fail`.

```php
use Mosaiqo\Proofread\Judge\JudgeAgent;

JudgeAgent::fake(score: 0.9, reason: 'deterministic stub');
```

**Faking the agent.** `laravel/ai` fakes work transparently:

```php
use Laravel\Ai\Facades\Ai;

Ai::fake([
    ['prompt' => 'I love this!', 'response' => 'positive'],
]);
```

**Persistence and assertions on the run.** After
`toPassEval`/`toPassSuite`, `$this->value` becomes the resulting
`EvalRun`. Chain follow-up expectations:

```php
expect($agent)->toPassEval($dataset, $assertions)
    ->toCostUnder(0.05)
    ->toCompleteIn(2000);
```

## CLI workflow

- `php artisan evals:run App\\Evals\\FooSuite --persist` — execute
  and persist to DB.
- `php artisan evals:run ... --junit=storage/evals/junit.xml` —
  emit JUnit for CI reporters.
- `php artisan evals:run ... --gate-pass-rate=0.9 --gate-cost-max=0.50`
  — hard CI gates.
- `php artisan evals:compare <base> <head> --format=markdown`
  — PR-comment-friendly diff.
- `php artisan evals:benchmark App\\Evals\\FooSuite --iterations=5`
  — stability and flakiness analysis.
- `php artisan evals:cluster` — group failing cases by embedding
  similarity.

## Commit hygiene

Keep evals deterministic in CI. When an LLM dependency is
unavoidable, pin the model version explicitly in the suite's
`subject()` or judge configuration and record it alongside the
persisted run (the `model` column captures this automatically).
Do not commit snapshots generated with `PROOFREAD_UPDATE_SNAPSHOTS=1`
without reviewing the diff first.

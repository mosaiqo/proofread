---
title: Assertions deep dive
section: Guides
---

# Assertions deep dive

Assertions are the unit of evaluation in Proofread. Each one inspects a
subject's output (and optionally the runtime context) and returns an
`AssertionResult` — a sealed value object carrying a boolean verdict, a
human-readable reason, an optional score, and a metadata bag. This guide
covers every assertion shipped with the package, when to reach for each
one, and the edge cases that trip people up in practice.

## The contract

Every concrete assertion implements
`Mosaiqo\Proofread\Contracts\Assertion`:

```php
namespace Mosaiqo\Proofread\Contracts;

use Mosaiqo\Proofread\Support\AssertionResult;

interface Assertion
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function run(mixed $output, array $context = []): AssertionResult;

    public function name(): string;
}
```

`$output` is whatever the subject returned — usually a string for text
agents, but could be an array, object, or even `null`. The runner does
not coerce; defensive `is_string()` / `gettype()` checks live inside
each assertion.

`$context` is a standard bag populated by the runner before `run()` is
called. Assertions read from it opportunistically:

| Key                 | Populated when                                  | Notes                               |
| ------------------- | ----------------------------------------------- | ----------------------------------- |
| `input`             | Always.                                         | The case's `input` value.           |
| `meta`              | When the case declares `meta`.                  | Arbitrary case annotations.         |
| `case_index`        | Always.                                         | Zero-based index in the dataset.    |
| `subject_label`     | Multi-provider runs.                            | The label from `subjects()`.        |
| `tokens_in`         | Agent subjects with usage.                      | Integer, may be `null`.             |
| `tokens_out`        | Agent subjects with usage.                      | Integer, may be `null`.             |
| `tokens_total`      | Agent subjects with usage.                      | Integer, may be `null`.             |
| `cost_usd`          | Agent subjects with a pricing table match.      | Float, may be `null`.               |
| `latency_ms`        | Always.                                         | Float, populated by the runner.     |
| `raw`               | Agent subjects.                                 | The raw `TextResponse`.             |

`AssertionResult::pass()` and `AssertionResult::fail()` are the only
ways to build a result. Both accept a reason, an optional score, and a
metadata array that surfaces in the dashboard drawer and exports.

> **[info]** The sealed hierarchy allows exactly one subclass:
> `JudgeResult`. Judge-backed assertions (`Rubric`, `Hallucination`,
> `Language`) return that richer type so the dashboard can display the
> judge model and retry count alongside the verdict.

## Deterministic

Fast, offline, zero-dependency. Reach for these first.

### ContainsAssertion

Plain substring match. The cheapest guard there is — good for sanity
checks ("the answer must mention the customer's name") and boilerplate
presence ("the email must include a signature").

```php
ContainsAssertion::make(string $needle, bool $caseSensitive = true): self
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\ContainsAssertion;

$assertion = ContainsAssertion::make('order confirmed');
```

Case-insensitive, multibyte-aware:

```php
use Mosaiqo\Proofread\Assertions\ContainsAssertion;

$assertion = ContainsAssertion::make('España', caseSensitive: false);
```

**Edge cases.**

- Empty-string needle is treated as always present (it passes). This
  matches `str_contains` semantics and prevents false failures when a
  case has no mandatory substring.
- Non-string output fails with `Expected string output, got <type>`.
- Case-insensitive comparison uses `mb_strtolower`, so accented
  characters compare correctly.

**Reason format.** `Output contains "<needle>"` on pass,
`Output does not contain "<needle>"` on fail.

### RegexAssertion

PCRE matching. Use it when a substring match is too loose and a full
JSON schema is too strict — structured identifiers, ISO dates, allow-
lists.

```php
RegexAssertion::make(string $pattern): self
```

The pattern must include delimiters (`/.../`, `#...#`, etc.) and is
validated at construction time: an invalid pattern throws
`InvalidArgumentException` rather than failing at run time.

Minimal:

```php
use Mosaiqo\Proofread\Assertions\RegexAssertion;

$assertion = RegexAssertion::make('/^order-\d{6}$/');
```

Complex — allow-list with case-insensitive and Unicode flags:

```php
use Mosaiqo\Proofread\Assertions\RegexAssertion;

$assertion = RegexAssertion::make('/^(positive|neutral|negative)$/iu');
```

**Edge cases.**

- Non-string output fails immediately.
- Runtime regex errors (backtrack limits, catastrophic patterns) are
  caught and surfaced as
  `Regex error while matching <pattern>: <preg_last_error_msg>`.
- Use `\A` / `\z` instead of `^` / `$` when matching multi-line output
  and you need end-of-string (not end-of-line) anchoring.

**Reason format.** `Output matches <pattern>` or
`Output does not match <pattern>`.

### LengthAssertion

Character count bounds. `mb_strlen` under the hood, so it counts user-
perceived characters, not bytes.

```php
LengthAssertion::min(int $min): self
LengthAssertion::max(int $max): self
LengthAssertion::between(int $min, int $max): self
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\LengthAssertion;

$assertion = LengthAssertion::max(280);
```

Complex — bound a summary:

```php
use Mosaiqo\Proofread\Assertions\LengthAssertion;

$assertions = [
    LengthAssertion::between(80, 200),
];
```

**Edge cases.**

- Negative bounds throw at construction.
- `min > max` throws at construction.
- Non-string output fails.

**Reason format.**
`Output length <n> is within bounds`, `Output length <n> is below
minimum <m>`, or `Output length <n> exceeds maximum <m>`.

### CountAssertion

Element count bounds for array / `Countable` output. Pair it with
subjects that return lists (extractors, taggers, search tools).

```php
CountAssertion::equals(int $count): self
CountAssertion::atLeast(int $min): self
CountAssertion::atMost(int $max): self
CountAssertion::between(int $min, int $max): self
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\CountAssertion;

$assertion = CountAssertion::atMost(5);
```

Complex — enforce an exact number of extracted entities:

```php
use Mosaiqo\Proofread\Assertions\CountAssertion;

$assertion = CountAssertion::between(1, 3);
```

**Edge cases.**

- Non-array, non-`Countable` output fails; `Countable::count()` is used
  when applicable.
- Negative bounds throw.

**Reason format.**
`Count <n> is within bounds`, `Count <n> is below minimum <m>`, or
`Count <n> exceeds maximum <m>`.

### JsonSchemaAssertion

Validates JSON-shaped output against an Opis JSON Schema. The assertion
accepts strings (parsed with `JSON_THROW_ON_ERROR`), arrays, and
objects; lists and nested associative arrays are normalized into stdClass
trees before validation.

```php
JsonSchemaAssertion::fromArray(array $schema): self
JsonSchemaAssertion::fromJson(string $json): self
JsonSchemaAssertion::fromFile(string $path): self
JsonSchemaAssertion::fromAgent(string $agentClass): self
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\JsonSchemaAssertion;

$assertion = JsonSchemaAssertion::fromArray([
    'type' => 'object',
    'required' => ['status'],
    'properties' => [
        'status' => ['type' => 'string', 'enum' => ['open', 'closed']],
    ],
]);
```

Complex — derive the schema straight from an Agent implementing
`HasStructuredOutput`:

```php
use App\Agents\ExtractInvoiceAgent;
use Mosaiqo\Proofread\Assertions\JsonSchemaAssertion;

$assertion = JsonSchemaAssertion::fromAgent(ExtractInvoiceAgent::class);
```

**Edge cases.**

- Top-level list schemas throw at construction; the validator expects
  an object.
- Malformed JSON input fails with the decoder message rather than a
  cryptic schema error.
- `fromAgent` instantiates the agent via the container to read its
  schema — side effects in the constructor are unsafe.

**Reason format.** `Output conforms to schema` on pass;
`Schema violation at <JSON pointer>: <opis message>` on fail. The
assertion dives into sub-errors and reports the deepest leaf so you
see the actual field that diverged rather than the root `anyOf`
umbrella error.

## Operational

Guard cost, speed, and token usage. These read values populated by the
runner and fail closed when data is missing — a subject that can't
report tokens cannot be budgeted, and silence shouldn't look like a
pass.

### TokenBudget

```php
TokenBudget::maxInput(int $tokens): self
TokenBudget::maxOutput(int $tokens): self
TokenBudget::maxTotal(int $tokens): self
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\TokenBudget;

$assertion = TokenBudget::maxTotal(2_000);
```

Complex — cap input and output separately:

```php
use Mosaiqo\Proofread\Assertions\TokenBudget;

$assertions = [
    TokenBudget::maxInput(1_500),
    TokenBudget::maxOutput(500),
];
```

**Edge cases.**

- Missing context keys (`tokens_in`, `tokens_out`, `tokens_total`) fail
  with an explicit diagnostic.
- `maxTotal` falls back to `tokens_in + tokens_out` when the runner
  provides only the parts.
- Closure subjects don't produce usage data; TokenBudget will always
  fail for them unless you synthesize usage yourself.

### CostLimit

```php
CostLimit::under(float $maxUsd): self
```

Reads `cost_usd` from the context. The runner computes it from the
pricing table configured in `config/proofread.php`, including cache-
read/write rates and reasoning-token pricing when present.

Minimal:

```php
use Mosaiqo\Proofread\Assertions\CostLimit;

$assertion = CostLimit::under(0.01);
```

Complex — combine with a token guard for belt-and-braces:

```php
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Assertions\TokenBudget;

$assertions = [
    CostLimit::under(0.0025),
    TokenBudget::maxTotal(1_000),
];
```

**Edge cases.**

- A model missing from the pricing table returns `null` cost; the
  assertion fails closed with a clear reason. Add the model to
  `proofread.pricing.models` to enable the check.
- `maxUsd <= 0` throws at construction.

**Reason format.** Dollar amounts are rendered with four decimal
places: `Cost $0.0043 is within limit of $0.0100`.

### LatencyLimit

```php
LatencyLimit::under(float $maxMs): self
```

Reads `latency_ms` from the context (always populated by the runner).

Minimal:

```php
use Mosaiqo\Proofread\Assertions\LatencyLimit;

$assertion = LatencyLimit::under(1_500);
```

**Edge cases.**

- Non-numeric `latency_ms` (should never happen via the runner) fails
  closed.
- Latency is end-to-end from subject invocation to return, including
  network round-trips. It does not separate first-byte from total
  duration.

## Semantic

LLM-as-judge and embedding-based. These call external services. All
three LLM-as-judge variants go through the shared `Judge` service, so
they share configuration (`proofread.judge.default_model`, retry count)
and testing ergonomics.

### Rubric

Scores output against a natural-language criteria string. The judge
returns `{passed, score, reason}`; the assertion layers a numeric
threshold on top.

```php
Rubric::make(string $criteria): self
Rubric::using(string $model): self       // override the judge model
Rubric::minScore(float $threshold): self // 0.0 – 1.0
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\Rubric;

$assertion = Rubric::make('The response must be polite and concise.');
```

Complex — pin a specific model and lower the threshold for a more
permissive pass:

```php
use Mosaiqo\Proofread\Assertions\Rubric;

$assertion = Rubric::make(
    'Explains the refund policy accurately and mentions the 14-day window.'
)
    ->using('claude-sonnet-4-6')
    ->minScore(0.75);
```

**Edge cases.**

- Judge failures (schema drift, network errors) exhaust retries and
  come back as `JudgeResult::fail` with `retryCount` reflecting the
  attempts made. The raw response from the last attempt is preserved
  in metadata under `judge_raw_response` for debugging.
- A judge verdict of `passed: true` still fails if `score < minScore`.
  The reason reads `Judge approved but score <x> is below threshold <y>`.
- `minScore` outside `[0.0, 1.0]` throws at construction.

**Reason format.** The judge's own `reason` field on pass; either the
judge's rejection reason or the threshold diagnostic on fail.

**Metadata.** `judge_model`, `judge_tokens_in`, `judge_tokens_out`,
`judge_cost_usd`, `judge_raw_response` (on failure), `retry_count`.

> **[info]** Test Rubric-backed suites without real LLM calls by faking
> the `JudgeAgent`:
>
> ```php
> use Mosaiqo\Proofread\Judge\JudgeAgent;
>
> JudgeAgent::fake(fn () => json_encode([
>     'passed' => true,
>     'score' => 0.95,
>     'reason' => 'Meets criteria.',
> ]));
> ```

### Similar

Embedding-based cosine similarity to a reference string. Use it when
the expected output is semantically fixed but verbally flexible.

```php
Similar::to(string $reference): self
Similar::using(string $model): self
Similar::minScore(float $threshold): self // -1.0 – 1.0
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\Similar;

$assertion = Similar::to('The package was delivered yesterday.');
```

Complex — tune the threshold and embedding model:

```php
use Mosaiqo\Proofread\Assertions\Similar;

$assertion = Similar::to('The package was delivered yesterday.')
    ->using('text-embedding-3-large')
    ->minScore(0.85);
```

**Edge cases.**

- Cosine similarity ranges from `-1.0` (opposite) to `1.0` (identical).
  Scores are usually positive for natural language but negative scores
  are valid and allowed.
- Default threshold is `0.8`, which is a reasonable starting point for
  `text-embedding-3-small`. Bump it when switching to a larger model.
- Non-string output fails fast with a type diagnostic.
- Embedding-service errors become a `fail` with metadata keys
  `embedding_model`, `embedding_cost_usd`, `embedding_tokens` set to
  `null` so downstream reporting doesn't crash on missing values.

**Reason format.** `Similarity <score> meets threshold <t>` on pass;
`Similarity <score> below threshold <t>` on fail.

### HallucinationAssertion

Judge-backed check that every claim in the output is supported by the
ground truth. Internally composes a fixed criteria string that instructs
the judge to treat the ground truth as authoritative.

```php
HallucinationAssertion::against(string $groundTruth): self
HallucinationAssertion::using(string $model): self
HallucinationAssertion::minScore(float $threshold): self // 0.0 – 1.0
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\HallucinationAssertion;

$assertion = HallucinationAssertion::against(
    'The order #12345 shipped on 2026-04-12 via DHL and is due on 2026-04-15.'
);
```

Complex — allow a slight deviation score:

```php
use Mosaiqo\Proofread\Assertions\HallucinationAssertion;

$assertion = HallucinationAssertion::against($kbSnippet)
    ->using('claude-sonnet-4-6')
    ->minScore(0.9);
```

**Edge cases.**

- Defaults to `minScore(1.0)` — any hallucination fails. Loosen it
  deliberately.
- Ground truth is truncation-free: large strings are forwarded as-is
  to the judge. Watch token costs when the source is a long KB article.

**Reason format.** The judge verdict; metadata matches Rubric.

### LanguageAssertion

Judge-backed language detector. Prefer it to a regex for genuine
natural-language output, which rarely has clean ASCII signals.

```php
LanguageAssertion::matches(string $languageCode): self
LanguageAssertion::using(string $model): self
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\LanguageAssertion;

$assertion = LanguageAssertion::matches('es');
```

Complex — use a full name and a cheaper judge:

```php
use Mosaiqo\Proofread\Assertions\LanguageAssertion;

$assertion = LanguageAssertion::matches('Portuguese')
    ->using('claude-haiku-4-5');
```

**Edge cases.**

- Input is normalized via `strtolower(trim(...))`; `'EN'`, `'en'`, and
  `' en '` all resolve identically.
- "Primarily in" means >80% of meaningful content per the internal
  prompt — mixed-language outputs with a short quote in another
  language still pass.

## Trajectory

### Trajectory

Validates how an Agent reached its answer, not just the answer itself.
Requires an `Agent` subject (closures don't produce step data). Reads
the `TextResponse` from `$context['raw']` and inspects `steps` and
`toolCalls`.

```php
Trajectory::maxSteps(int $max): self
Trajectory::minSteps(int $min): self
Trajectory::stepsBetween(int $min, int $max): self
Trajectory::callsTool(string $name): self
Trajectory::doesNotCallTool(string $name): self
Trajectory::callsTools(array $names): self          // any order
Trajectory::callsToolsInOrder(array $names): self   // strict order
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\Trajectory;

$assertion = Trajectory::maxSteps(3);
```

Complex — enforce a required tool chain:

```php
use Mosaiqo\Proofread\Assertions\Trajectory;

$assertions = [
    Trajectory::stepsBetween(2, 5),
    Trajectory::callsToolsInOrder(['lookup_order', 'open_refund']),
    Trajectory::doesNotCallTool('escalate_to_human'),
];
```

**Edge cases.**

- Non-agent subjects produce
  `Trajectory requires an Agent subject — got <type>`.
- `callsTools` accepts any observed order. `callsToolsInOrder` allows
  interleaved extra calls as long as the required subsequence appears
  in order.
- Empty tool lists throw at construction.

**Reason format.** Always includes the observed tools on failure so you
can diagnose immediately without opening the drawer:
`Trajectory did not call required tool "lookup_order" (observed: search, explain)`.

## Snapshot

### GoldenSnapshot

Captures a reference output on first run, then compares subsequent runs
against it. Use it for prompts whose output should remain byte-stable
across refactors (templates, deterministic pipelines).

```php
GoldenSnapshot::forKey(string $key): self
GoldenSnapshot::fromContext(): self
```

Minimal:

```php
use Mosaiqo\Proofread\Assertions\GoldenSnapshot;

$assertion = GoldenSnapshot::forKey('invoice-summary-v1');
```

Complex — derive the key from `meta.name` or `case_index`:

```php
use Mosaiqo\Proofread\Assertions\GoldenSnapshot;

$assertion = GoldenSnapshot::fromContext();
```

**Edge cases.**

- On first run the snapshot is written and the assertion passes with
  `Snapshot '<key>' created` and metadata `snapshot_created: true`.
- When the environment variable `PROOFREAD_UPDATE_SNAPSHOTS=true` is
  set, a mismatched snapshot is overwritten instead of failing; the
  result carries `snapshot_updated: true` so CI can detect accidental
  rebaselines.
- Diffs are line-based with `-` / `+` prefixes and are truncated to
  30 lines (15 head + "… N more lines"). Lines over 120 chars are
  also truncated. The full diff is never persisted — only the rendered
  excerpt.
- `fromContext()` looks up `meta.snapshot_key`, then `meta.name`, then
  falls back to `case_<index>`; none of the three being present fails
  with a clear diagnostic.
- Snapshots live under `config('proofread.snapshots.path')`, which
  defaults to `tests/Snapshots/proofread`. Commit the directory so the
  baseline is shared.

**Reason format.** `Snapshot '<key>' matches` on pass;
`Snapshot '<key>' does not match:\n<diff>` on fail. Metadata always
includes `snapshot_key` and `snapshot_path`; on fail,
`snapshot_diff` carries the rendered excerpt.

## Structured

### StructuredOutputAssertion

Opinionated counterpart to `JsonSchemaAssertion::fromAgent()`. Derives
the schema from an Agent implementing `HasStructuredOutput` and
produces LLM-tailored error messages. Also stores the parsed output
under the `parsed_data` metadata key so downstream tooling (clusterers,
dataset generators) can read structured values without reparsing.

```php
StructuredOutputAssertion::conformsTo(string $agentClass): self
```

Minimal:

```php
use App\Agents\ClassifyTicketAgent;
use Mosaiqo\Proofread\Assertions\StructuredOutputAssertion;

$assertion = StructuredOutputAssertion::conformsTo(ClassifyTicketAgent::class);
```

**Edge cases.**

- The agent class must exist and implement `HasStructuredOutput`; both
  conditions throw at construction when unmet.
- Failure metadata includes both `parsed_data` and `violation_path`
  (JSON pointer), which the dashboard uses to highlight the offending
  field in the drawer.

**Reason format.**
`Output conforms to structured schema of <ShortName>` on pass;
`Structured output violation at <path>: <message>` on fail.

## Safety

### PiiLeakageAssertion

Runs the configured `PiiSanitizer` over the output and fails if any
placeholder was inserted. Use it to catch the reverse of sanitization:
the model leaking PII into a response it shouldn't.

```php
PiiLeakageAssertion::make(?PiiSanitizer $sanitizer = null): self
PiiLeakageAssertion::withPatterns(array $redactPatterns): self
```

Minimal (uses the container-resolved sanitizer, which reads
`proofread.shadow.sanitize`):

```php
use Mosaiqo\Proofread\Assertions\PiiLeakageAssertion;

$assertion = PiiLeakageAssertion::make();
```

Complex — bespoke patterns scoped to this suite:

```php
use Mosaiqo\Proofread\Assertions\PiiLeakageAssertion;

$assertion = PiiLeakageAssertion::withPatterns([
    '/\b\d{3}-\d{2}-\d{4}\b/' => '[SSN]',
    '/\b[A-Z]{2}\d{6}[A-Z]\b/' => '[PASSPORT]',
]);
```

**Edge cases.**

- Only string output is supported. Nested array leakage belongs on the
  input side (handled by the shadow capture path), not the assertion.
- The assertion considers the output "leaked" as soon as at least one
  placeholder ends up in the sanitized string. Metadata lists every
  placeholder found so the drawer can tell you which PII classes
  appeared.

**Reason format.** `No PII patterns detected` on pass;
`PII detected in output: [EMAIL], [CARD]` on fail.

**Metadata.** `placeholders_found`, `sanitized_output`.

## Writing custom assertions

The `Assertion` contract is tiny. A bespoke assertion is often fewer
than 30 lines.

```php
<?php

declare(strict_types=1);

namespace App\Evals\Assertions;

use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

final readonly class ExactCountMatches implements Assertion
{
    private function __construct(private string $metaKey) {}

    public static function from(string $metaKey): self
    {
        return new self($metaKey);
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        $expected = $context['meta'][$this->metaKey] ?? null;

        if (! is_int($expected)) {
            return AssertionResult::fail(sprintf(
                'ExactCountMatches requires integer meta.%s', $this->metaKey
            ));
        }

        if (! is_array($output)) {
            return AssertionResult::fail(sprintf(
                'Expected array output, got %s', gettype($output)
            ));
        }

        $actual = count($output);

        return $actual === $expected
            ? AssertionResult::pass(sprintf('Count %d matches expected', $actual))
            : AssertionResult::fail(sprintf(
                'Count %d does not match expected %d', $actual, $expected
            ));
    }

    public function name(): string
    {
        return 'exact_count_matches';
    }
}
```

Scaffolded:

```bash
php artisan proofread:make-assertion ExactCountMatches
```

The generator drops a stub into `app/Evals/Assertions/` wired up with
strict types, a `final readonly` declaration, named constructors, and a
Pest test file mirroring the class path.

> **[info]** Design custom assertions as readonly value objects with
> named constructors. The runner invokes `run()` repeatedly and may
> serialize the assertion (via `name()`) into persistence — immutable
> objects make this trivial.

## See also

- [Assertions overview](/docs/assertions) — quick reference matrix.
- [Eval suites](/docs/eval-suites) — wiring assertions into cases.
- [Shadow evals](/docs/guides/shadow-evals) — running assertions
  against production traffic.
- [Multi-provider comparison](/docs/guides/multi-provider) — branching
  assertions per subject.

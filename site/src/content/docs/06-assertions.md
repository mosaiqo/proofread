---
title: Assertions
section: Concepts
---

# Assertions

Proofread ships 15+ assertions across 7 categories. All implement the
`Mosaiqo\Proofread\Contracts\Assertion` interface and return an
`AssertionResult`. Mix and match them per case.

## Deterministic

Zero-dependency, no LLM calls. Fast and cheap.

| Assertion                  | Example                                                     | Description                                      |
| -------------------------- | ----------------------------------------------------------- | ------------------------------------------------ |
| `ContainsAssertion`        | `ContainsAssertion::make('positive')`                       | Substring match (case-sensitive by default).     |
| `RegexAssertion`           | `RegexAssertion::make('/^\w+$/')`                           | PCRE pattern match.                              |
| `LengthAssertion`          | `LengthAssertion::between(1, 200)`                          | String length within `[min, max]`.               |
| `CountAssertion`           | `CountAssertion::equals(3)`                                 | Element count of an array/iterable output.       |
| `JsonSchemaAssertion`      | `JsonSchemaAssertion::fromAgent(MyStructuredAgent::class)`  | Validate JSON output against a JSON Schema.      |

```php
use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Assertions\CountAssertion;
use Mosaiqo\Proofread\Assertions\JsonSchemaAssertion;
use Mosaiqo\Proofread\Assertions\LengthAssertion;
use Mosaiqo\Proofread\Assertions\RegexAssertion;

$assertions = [
    ContainsAssertion::make('hello'),
    RegexAssertion::make('/^(yes|no)$/i'),
    LengthAssertion::between(1, 500),
    CountAssertion::atMost(10),
    JsonSchemaAssertion::fromAgent(ExtractInvoiceAgent::class),
];
```

## Operational

Cost, speed, and token guards.

| Assertion       | Example                                 | Description                           |
| --------------- | --------------------------------------- | ------------------------------------- |
| `TokenBudget`   | `TokenBudget::maxTotal(2_000)`          | Cap input/output/total tokens.        |
| `CostLimit`     | `CostLimit::under(0.01)`                | Cap cost per case in USD.             |
| `LatencyLimit`  | `LatencyLimit::under(1_500)`            | Cap latency per case in milliseconds. |

```php
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Assertions\LatencyLimit;
use Mosaiqo\Proofread\Assertions\TokenBudget;

$assertions = [
    TokenBudget::maxTotal(2_000),
    CostLimit::under(0.005),
    LatencyLimit::under(1_500),
];
```

## Semantic

LLM-as-judge and embedding-based. Require a judge agent configured in
`config/proofread.php`.

| Assertion                 | Example                                         | Description                                          |
| ------------------------- | ----------------------------------------------- | ---------------------------------------------------- |
| `Rubric`                  | `Rubric::make('is polite and concise')`         | LLM-as-judge. Pass/fail per a criteria string.       |
| `Similar`                 | `Similar::to('expected text')`                  | Embedding cosine similarity to a reference.          |
| `HallucinationAssertion`  | `HallucinationAssertion::against($groundTruth)` | Detects claims unsupported by ground truth.          |
| `LanguageAssertion`       | `LanguageAssertion::matches('en')`              | Verifies output language matches an ISO 639-1 code.  |

```php
use Mosaiqo\Proofread\Assertions\HallucinationAssertion;
use Mosaiqo\Proofread\Assertions\LanguageAssertion;
use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Assertions\Similar;

$assertions = [
    Rubric::make('is polite and on-topic'),
    Similar::to('The refund will be processed in 3 business days.')->minScore(0.82),
    HallucinationAssertion::against($groundTruth)->minScore(0.9),
    LanguageAssertion::matches('en'),
];
```

## Trajectory

Verifies how an agent reached its answer, not just the answer.

| Assertion     | Example                                          | Description                                  |
| ------------- | ------------------------------------------------ | -------------------------------------------- |
| `Trajectory`  | `Trajectory::callsToolsInOrder(['search','summarize'])` | Step count and tool-call sequence checks. |

```php
use Mosaiqo\Proofread\Assertions\Trajectory;

$assertions = [
    Trajectory::maxSteps(5),
    Trajectory::callsTool('search_knowledge_base'),
    Trajectory::doesNotCallTool('send_email'),
];
```

## Snapshot

Golden-file regression testing.

| Assertion        | Example                       | Description                                               |
| ---------------- | ----------------------------- | --------------------------------------------------------- |
| `GoldenSnapshot` | `GoldenSnapshot::fromContext()` | Compares output against a stored snapshot. Auto-updates on first run. |

```php
use Mosaiqo\Proofread\Assertions\GoldenSnapshot;

$assertions = [
    GoldenSnapshot::fromContext(),
];
```

## Structured

| Assertion                     | Example                                                          | Description                                                   |
| ----------------------------- | ---------------------------------------------------------------- | ------------------------------------------------------------- |
| `StructuredOutputAssertion`   | `StructuredOutputAssertion::conformsTo(MyAgent::class)`          | Validates that output conforms to an agent's `HasStructuredOutput` declaration. |

## Safety

| Assertion              | Example                      | Description                                     |
| ---------------------- | ---------------------------- | ----------------------------------------------- |
| `PiiLeakageAssertion`  | `PiiLeakageAssertion::make()` | Flags outputs containing PII (emails, phones, SSNs, credit cards). |

```php
use Mosaiqo\Proofread\Assertions\PiiLeakageAssertion;

$assertions = [
    PiiLeakageAssertion::make(),
];
```

> **[info]** **Per-case assertions:** override `assertionsFor(array $case):
> array` in your suite to vary assertions based on case metadata (for
> example, run a stricter latency cap on cases tagged `meta.tier => 'critical'`).

## Deep dives

Each assertion has edge cases and configuration details worth their own
guide. Those guides ship alongside the runtime guides; start here for the
overview and reach for them when you hit a specific need.

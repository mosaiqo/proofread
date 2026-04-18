const n=`---
title: "Assertions API"
section: "API Reference"
---

# Assertions API

> **[info]** This page is auto-generated from PHP docblocks.
> Regenerate with \`composer docs:api\` after editing any docblock.

Concrete assertion classes shipped with Proofread. All implement the \`Assertion\` interface and return an \`AssertionResult\` (or \`JudgeResult\` for LLM-as-judge).

## Summary

| Class | Kind | Description |
|---|---|---|
| [\`ContainsAssertion\`](#containsassertion) | Final class | — |
| [\`CostLimit\`](#costlimit) | Final class | — |
| [\`CountAssertion\`](#countassertion) | Final class | — |
| [\`GoldenSnapshot\`](#goldensnapshot) | Final class | — |
| [\`HallucinationAssertion\`](#hallucinationassertion) | Final class | LLM-as-judge assertion that fails when the output contains claims, facts, or details not present in or derivable from the provided ground truth. |
| [\`JsonSchemaAssertion\`](#jsonschemaassertion) | Final class | — |
| [\`LanguageAssertion\`](#languageassertion) | Final class | LLM-as-judge assertion that verifies the output is primarily written in the expected language. Accepts ISO 639-1 codes (\`en\`, \`es\`) or common names (\`English\`, \`Spanish\`); language identifier is normalized to lowercase. |
| [\`LatencyLimit\`](#latencylimit) | Final class | — |
| [\`LengthAssertion\`](#lengthassertion) | Final class | — |
| [\`PiiLeakageAssertion\`](#piileakageassertion) | Final class | Deterministic PII leakage check. Applies the configured {@see PiiSanitizer} redaction patterns to the output and fails when at least one placeholder has been inserted. |
| [\`RegexAssertion\`](#regexassertion) | Final class | — |
| [\`Rubric\`](#rubric) | Final class | LLM-as-judge assertion that scores output against natural-language criteria. |
| [\`Similar\`](#similar) | Final class | — |
| [\`StructuredOutputAssertion\`](#structuredoutputassertion) | Final class | Assertion that validates an agent's output conforms to the JSON schema it declares via {@see HasStructuredOutput}. Produces error messages and metadata tailored to the "LLM must return structured JSON" scenario. |
| [\`TokenBudget\`](#tokenbudget) | Final class | — |
| [\`Trajectory\`](#trajectory) | Final class | — |

---

## \`ContainsAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/ContainsAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/ContainsAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`make()\`

\`\`\`php
public static function make(string $needle, bool $caseSensitive = true): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`string $needle\`
- readonly \`bool $caseSensitive\`

---

## \`CostLimit\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/CostLimit.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/CostLimit.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`under()\`

\`\`\`php
public static function under(float $maxUsd): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`float $maxUsd\`

---

## \`CountAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/CountAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/CountAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`equals()\`

\`\`\`php
public static function equals(int $count): self
\`\`\`

#### \`atLeast()\`

\`\`\`php
public static function atLeast(int $min): self
\`\`\`

#### \`atMost()\`

\`\`\`php
public static function atMost(int $max): self
\`\`\`

#### \`between()\`

\`\`\`php
public static function between(int $min, int $max): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`?int $min\`
- readonly \`?int $max\`

---

## \`GoldenSnapshot\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/GoldenSnapshot.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/GoldenSnapshot.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`forKey()\`

\`\`\`php
public static function forKey(string $key): self
\`\`\`

#### \`fromContext()\`

\`\`\`php
public static function fromContext(): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

---

## \`HallucinationAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/HallucinationAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/HallucinationAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

LLM-as-judge assertion that fails when the output contains claims, facts,
or details not present in or derivable from the provided ground truth.

## Testing with a faked judge

\`\`\`php
use Mosaiqo\\Proofread\\Judge\\JudgeAgent;

beforeEach(function (): void {
    JudgeAgent::fake(fn () => json_encode([
        'passed' => true,
        'score' => 1.0,
        'reason' => 'All claims grounded.',
    ]));
});
\`\`\`

### Named constructors & static methods

#### \`against()\`

\`\`\`php
public static function against(string $groundTruth): self
\`\`\`

### Methods

#### \`using()\`

\`\`\`php
public function using(string $model): self
\`\`\`

#### \`minScore()\`

\`\`\`php
public function minScore(float $threshold): self
\`\`\`

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): JudgeResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`string $groundTruth\`
- readonly \`?string $model\`
- readonly \`float $minScore\`

---

## \`JsonSchemaAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/JsonSchemaAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/JsonSchemaAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`fromArray()\`

\`\`\`php
public static function fromArray(array $schema): self
\`\`\`

#### \`fromJson()\`

\`\`\`php
public static function fromJson(string $json): self
\`\`\`

#### \`fromAgent()\`

\`\`\`php
public static function fromAgent(string $agentClass): self
\`\`\`

Build an assertion from the structured-output schema declared by an Agent.

#### \`fromFile()\`

\`\`\`php
public static function fromFile(string $path): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

---

## \`LanguageAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/LanguageAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/LanguageAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

LLM-as-judge assertion that verifies the output is primarily written in the
expected language. Accepts ISO 639-1 codes (\`en\`, \`es\`) or common names
(\`English\`, \`Spanish\`); language identifier is normalized to lowercase.

### Named constructors & static methods

#### \`matches()\`

\`\`\`php
public static function matches(string $languageCode): self
\`\`\`

### Methods

#### \`using()\`

\`\`\`php
public function using(string $model): self
\`\`\`

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): JudgeResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`string $language\`
- readonly \`?string $model\`

---

## \`LatencyLimit\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/LatencyLimit.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/LatencyLimit.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`under()\`

\`\`\`php
public static function under(float $maxMs): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`float $maxMs\`

---

## \`LengthAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/LengthAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/LengthAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`min()\`

\`\`\`php
public static function min(int $min): self
\`\`\`

#### \`max()\`

\`\`\`php
public static function max(int $max): self
\`\`\`

#### \`between()\`

\`\`\`php
public static function between(int $min, int $max): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`?int $min\`
- readonly \`?int $max\`

---

## \`PiiLeakageAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/PiiLeakageAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/PiiLeakageAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

Deterministic PII leakage check. Applies the configured {@see PiiSanitizer}
redaction patterns to the output and fails when at least one placeholder
has been inserted.

Only string outputs are supported because {@see \\PiiSanitizer::sanitizeOutput}
operates on strings. Array/object traversal via PII keys is a concern of
shadow input sanitization, not output leakage detection.

### Named constructors & static methods

#### \`make()\`

\`\`\`php
public static function make(?PiiSanitizer $sanitizer = null): self
\`\`\`

#### \`withPatterns()\`

\`\`\`php
public static function withPatterns(array $redactPatterns): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

---

## \`RegexAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/RegexAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/RegexAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`make()\`

\`\`\`php
public static function make(string $pattern): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`string $pattern\`

---

## \`Rubric\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/Rubric.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/Rubric.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

LLM-as-judge assertion that scores output against natural-language criteria.

## Testing with a faked judge

When testing code that uses this assertion, avoid real LLM calls by faking
the judge agent. Proofread uses an internal {@see \\JudgeAgent}
class that extends the Laravel AI SDK's Agent contract, so you can fake it
with the SDK's fake helper:

\`\`\`php
use Mosaiqo\\Proofread\\Judge\\JudgeAgent;

beforeEach(function () {
    JudgeAgent::fake(fn () => json_encode([
        'passed' => true,
        'score' => 0.95,
        'reason' => 'Meets criteria.',
    ]));
});
\`\`\`

Make sure \`config('ai.default')\` points to any provider and that
\`config('proofread.judge.default_model')\` is set — both are loaded by the
default service provider and are typically already in place.

### Named constructors & static methods

#### \`make()\`

\`\`\`php
public static function make(string $criteria): self
\`\`\`

### Methods

#### \`using()\`

\`\`\`php
public function using(string $model): self
\`\`\`

#### \`minScore()\`

\`\`\`php
public function minScore(float $threshold): self
\`\`\`

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): JudgeResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`string $criteria\`
- readonly \`?string $model\`
- readonly \`float $minScore\`

---

## \`Similar\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/Similar.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/Similar.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`to()\`

\`\`\`php
public static function to(string $reference): self
\`\`\`

### Methods

#### \`using()\`

\`\`\`php
public function using(string $model): self
\`\`\`

#### \`minScore()\`

\`\`\`php
public function minScore(float $threshold): self
\`\`\`

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`string $reference\`
- readonly \`?string $model\`
- readonly \`float $minScore\`

---

## \`StructuredOutputAssertion\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/StructuredOutputAssertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/StructuredOutputAssertion.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

Assertion that validates an agent's output conforms to the JSON schema
it declares via {@see HasStructuredOutput}. Produces error messages and
metadata tailored to the "LLM must return structured JSON" scenario.

### Named constructors & static methods

#### \`conformsTo()\`

\`\`\`php
public static function conformsTo(string $agentClass): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

---

## \`TokenBudget\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/TokenBudget.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/TokenBudget.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`maxInput()\`

\`\`\`php
public static function maxInput(int $tokens): self
\`\`\`

#### \`maxOutput()\`

\`\`\`php
public static function maxOutput(int $tokens): self
\`\`\`

#### \`maxTotal()\`

\`\`\`php
public static function maxTotal(int $tokens): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`

### Public properties

- readonly \`?int $maxInput\`
- readonly \`?int $maxOutput\`
- readonly \`?int $maxTotal\`

---

## \`Trajectory\`

- **Kind:** Final class
- **Namespace:** \`Mosaiqo\\Proofread\\Assertions\`
- **Source:** [src/Assertions/Trajectory.php](https://github.com/mosaiqo/proofread/blob/main/src/Assertions/Trajectory.php)
- **Implements:** \`Mosaiqo\\Proofread\\Contracts\\Assertion\`

### Named constructors & static methods

#### \`maxSteps()\`

\`\`\`php
public static function maxSteps(int $max): self
\`\`\`

#### \`minSteps()\`

\`\`\`php
public static function minSteps(int $min): self
\`\`\`

#### \`stepsBetween()\`

\`\`\`php
public static function stepsBetween(int $min, int $max): self
\`\`\`

#### \`callsTool()\`

\`\`\`php
public static function callsTool(string $name): self
\`\`\`

#### \`doesNotCallTool()\`

\`\`\`php
public static function doesNotCallTool(string $name): self
\`\`\`

#### \`callsTools()\`

\`\`\`php
public static function callsTools(array $names): self
\`\`\`

#### \`callsToolsInOrder()\`

\`\`\`php
public static function callsToolsInOrder(array $names): self
\`\`\`

### Methods

#### \`run()\`

\`\`\`php
public function run(mixed $output, array $context = []): AssertionResult
\`\`\`

#### \`name()\`

\`\`\`php
public function name(): string
\`\`\`
`;export{n as default};

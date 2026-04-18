---
title: "Value objects & helpers"
section: "API Reference"
---

# Value objects & helpers

> **[info]** This page is auto-generated from PHP docblocks.
> Regenerate with `composer docs:api` after editing any docblock.

Immutable value objects and helper types used throughout the runner, lint, coverage, CLI subjects, and diff subsystems.

## Summary

| Class | Kind | Description |
|---|---|---|
| [`AmbiguityRule`](#ambiguityrule) | Final class | — |
| [`CaseCoverage`](#casecoverage) | Final class | Per-dataset-case coverage statistics: how many shadow captures landed nearest to this case and the average cosine similarity of those matches. |
| [`CaseDelta`](#casedelta) | Final class | — |
| [`ClaudeCodeCliSubject`](#claudecodeclisubject) | Final class | — |
| [`CliExecutionException`](#cliexecutionexception) | Final class | — |
| [`CliInvocation`](#cliinvocation) | Final class | — |
| [`CliResponse`](#cliresponse) | Final class | — |
| [`CliSubject`](#clisubject) | Abstract class | — |
| [`CliTimeoutException`](#clitimeoutexception) | Final class | — |
| [`ComparisonResolver`](#comparisonresolver) | Final class | Resolve a textual reference into a persisted EvalComparison model. |
| [`ContradictionRule`](#contradictionrule) | Final class | Conservative heuristic for detecting "always X" vs "never X" contradictions. |
| [`CostProjection`](#costprojection) | Final class | Projected cost for a single model computed from a set of shadow captures. |
| [`CostSimulationReport`](#costsimulationreport) | Final class | Aggregate cost-simulation report for one agent over a time window: the current model's projected cost plus projections for alternative models. |
| [`CostSimulator`](#costsimulator) | Final class | Simulate the cost of running an agent's historical traffic under different models. Takes the persisted ShadowCapture rows within a window, computes the cost under the captures' actual model (the "current" projection) and under a set of alternative models using the configured PricingTable. |
| [`CoverageAnalyzer`](#coverageanalyzer) | Final class | Measure how well a dataset reflects production traffic by comparing the embeddings of the dataset's case inputs against the embeddings of the agent's recent shadow captures. |
| [`CoverageReport`](#coveragereport) | Final class | Aggregate coverage report: for one agent, over a time window, it answers "which dataset cases reflect production traffic and which captures have no dataset analogue?". |
| [`Dataset`](#dataset) | Final class | — |
| [`DatasetGenerator`](#datasetgenerator) | Final class | — |
| [`DatasetGeneratorAgent`](#datasetgeneratoragent) | Final class | — |
| [`DatasetGeneratorException`](#datasetgeneratorexception) | Final class | — |
| [`EvalComparison`](#evalcomparison) | Final class | — |
| [`EvalResult`](#evalresult) | Final class | — |
| [`EvalRun`](#evalrun) | Final class | — |
| [`EvalRunDelta`](#evalrundelta) | Final class | — |
| [`EvalRunDiff`](#evalrundiff) | Final class | Computes a structured diff between two persisted EvalRun models. |
| [`FailureClusterer`](#failureclusterer) | Final class | Group failure signals into clusters of semantically similar items using threshold-based single-pass clustering over embedding cosine similarity. |
| [`JudgeResult`](#judgeresult) | Final class | — |
| [`LengthRule`](#lengthrule) | Final class | — |
| [`LintIssue`](#lintissue) | Final class | — |
| [`LintReport`](#lintreport) | Final class | — |
| [`LintRule`](#lintrule) | Interface | — |
| [`MissingOutputFormatRule`](#missingoutputformatrule) | Final class | — |
| [`MissingRoleRule`](#missingrolerule) | Final class | — |
| [`PromptLinter`](#promptlinter) | Final class | — |
| [`RunResolver`](#runresolver) | Final class | Resolve a textual reference into a persisted EvalRun model. |
| [`SemanticQualityRule`](#semanticqualityrule) | Final class | LLM-based semantic analysis of an Agent's instructions. |
| [`UncoveredCapture`](#uncoveredcapture) | Final class | A shadow capture that is not covered by any dataset case: its max cosine similarity against every case is below the configured threshold. |

---

## `AmbiguityRule`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint\Rules`
- **Source:** [src/Lint/Rules/AmbiguityRule.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/Rules/AmbiguityRule.php)
- **Implements:** `Mosaiqo\Proofread\Lint\Contracts\LintRule`

### Methods

#### `name()`

```php
public function name(): string
```

#### `check()`

```php
public function check(Agent $agent, string $instructions): array
```

---

## `CaseCoverage`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Coverage`
- **Source:** [src/Coverage/CaseCoverage.php](https://github.com/mosaiqo/proofread/blob/main/src/Coverage/CaseCoverage.php)

Per-dataset-case coverage statistics: how many shadow captures landed
nearest to this case and the average cosine similarity of those matches.

### Methods

#### `__construct()`

```php
public function __construct(int $caseIndex, ?string $caseName, int $matchedCaptures, float $avgSimilarity)
```

### Public properties

- readonly `int $caseIndex`
- readonly `?string $caseName`
- readonly `int $matchedCaptures`
- readonly `float $avgSimilarity`

---

## `CaseDelta`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Diff`
- **Source:** [src/Diff/CaseDelta.php](https://github.com/mosaiqo/proofread/blob/main/src/Diff/CaseDelta.php)

### Methods

#### `__construct()`

```php
public function __construct(int $caseIndex, ?string $caseName, bool $basePassed, bool $headPassed, string $status, ?float $baseCostUsd, ?float $headCostUsd, ?float $baseDurationMs, ?float $headDurationMs, array $newFailures, array $fixedFailures)
```

#### `toArray()`

```php
public function toArray(): array
```

Canonical JSON-friendly array shape shared by the MCP tool,
evals:compare CLI, and regression webhook generic payload.

### Public properties

- readonly `int $caseIndex`
- readonly `?string $caseName`
- readonly `bool $basePassed`
- readonly `bool $headPassed`
- readonly `string $status`
- readonly `?float $baseCostUsd`
- readonly `?float $headCostUsd`
- readonly `?float $baseDurationMs`
- readonly `?float $headDurationMs`
- readonly `array $newFailures`
- readonly `array $fixedFailures`

---

## `ClaudeCodeCliSubject`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Cli\Subjects`
- **Source:** [src/Cli/Subjects/ClaudeCodeCliSubject.php](https://github.com/mosaiqo/proofread/blob/main/src/Cli/Subjects/ClaudeCodeCliSubject.php)
- **Extends:** `Mosaiqo\Proofread\Cli\CliSubject`

### Named constructors & static methods

#### `make()`

```php
public static function make(): self
```

### Methods

#### `__construct()`

```php
public function __construct(string $binaryPath = 'claude', ?string $model = null, int $timeoutSeconds = 120, bool $dangerouslySkipPermissions = false, array $extraArgs = [], array $envVars = [])
```

#### `withBinary()`

```php
public function withBinary(string $binary): self
```

#### `withModel()`

```php
public function withModel(string $model): self
```

#### `withTimeout()`

```php
public function withTimeout(int $seconds): self
```

#### `skipPermissions()`

```php
public function skipPermissions(bool $skip = true): self
```

#### `withArgs()`

```php
public function withArgs(array $args): self
```

#### `withEnv()`

```php
public function withEnv(array $env): self
```

#### `binary()`

```php
public function binary(): string
```

#### `args()`

```php
public function args(string $prompt): array
```

#### `timeout()`

```php
public function timeout(): int
```

#### `env()`

```php
public function env(): array
```

#### `parseOutput()`

```php
public function parseOutput(string $stdout, string $stderr): CliResponse
```

---

## `CliExecutionException`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Cli`
- **Source:** [src/Cli/CliExecutionException.php](https://github.com/mosaiqo/proofread/blob/main/src/Cli/CliExecutionException.php)
- **Extends:** `RuntimeException`
- **Implements:** `Stringable`, `Throwable`

---

## `CliInvocation`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Cli`
- **Source:** [src/Cli/CliInvocation.php](https://github.com/mosaiqo/proofread/blob/main/src/Cli/CliInvocation.php)

### Methods

#### `__construct()`

```php
public function __construct(string $output, string $stdout, string $stderr, int $exitCode, float $durationMs, array $metadata = [])
```

### Public properties

- readonly `string $output`
- readonly `string $stdout`
- readonly `string $stderr`
- readonly `int $exitCode`
- readonly `float $durationMs`
- readonly `array $metadata`

---

## `CliResponse`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Cli`
- **Source:** [src/Cli/CliResponse.php](https://github.com/mosaiqo/proofread/blob/main/src/Cli/CliResponse.php)

### Methods

#### `__construct()`

```php
public function __construct(string $output, array $metadata = [])
```

### Public properties

- readonly `string $output`
- readonly `array $metadata`

---

## `CliSubject`

- **Kind:** Abstract class
- **Namespace:** `Mosaiqo\Proofread\Cli`
- **Source:** [src/Cli/CliSubject.php](https://github.com/mosaiqo/proofread/blob/main/src/Cli/CliSubject.php)

### Methods

#### `binary()`

```php
public abstract function binary(): string
```

Absolute path or name (resolvable via PATH) of the CLI binary.

#### `args()`

```php
public abstract function args(string $prompt): array
```

Arguments to pass to the binary. Returned as array of strings
for shell-safe execution (no shell interpolation).

#### `parseOutput()`

```php
public abstract function parseOutput(string $stdout, string $stderr): CliResponse
```

Parse stdout + stderr into the assistant's response and optional
metadata. Invoked regardless of exit code — implementations decide
whether a non-zero exit is a failure or part of normal output.

#### `timeout()`

```php
public function timeout(): int
```

Process timeout in seconds. Default 120s.

#### `workingDirectory()`

```php
public function workingDirectory(): ?string
```

Working directory for the subprocess. Default null = current.

#### `env()`

```php
public function env(): array
```

Extra environment variables for the subprocess.

#### `usesStdin()`

```php
public function usesStdin(): bool
```

Whether to pass the prompt via stdin instead of args. Default false.

#### `estimateTokens()`

```php
public function estimateTokens(string $text): int
```

Estimate token count from a string. Default heuristic: word count / 0.75.

Override when the CLI provides real counts.

---

## `CliTimeoutException`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Cli`
- **Source:** [src/Cli/CliTimeoutException.php](https://github.com/mosaiqo/proofread/blob/main/src/Cli/CliTimeoutException.php)
- **Extends:** `RuntimeException`
- **Implements:** `Stringable`, `Throwable`

---

## `ComparisonResolver`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Support`
- **Source:** [src/Support/ComparisonResolver.php](https://github.com/mosaiqo/proofread/blob/main/src/Support/ComparisonResolver.php)

Resolve a textual reference into a persisted EvalComparison model.

Accepted reference forms:
- Full ULID (26 chars) — matched exactly against the comparison id.
- Commit SHA prefix (4-40 hex chars) — matched via prefix against
  the commit_sha column, most recent first.
- Literal "latest" — the most recently created comparison in the
  database.

### Methods

#### `resolve()`

```php
public function resolve(string $identifier): ?EvalComparison
```

---

## `ContradictionRule`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint\Rules`
- **Source:** [src/Lint/Rules/ContradictionRule.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/Rules/ContradictionRule.php)
- **Implements:** `Mosaiqo\Proofread\Lint\Contracts\LintRule`

Conservative heuristic for detecting "always X" vs "never X" contradictions.

Implementation extracts short phrases following "always" or "never" and
compares their lowercased content-word sets. When the shorter phrase's
content words are largely contained in the longer one (ratio above 0.5),
the rule flags a contradiction. Shorter-phrase containment is used rather
than symmetric Jaccard overlap because scoped exceptions ("never X when Y")
still contradict the unconditional rule ("always X").

### Methods

#### `name()`

```php
public function name(): string
```

#### `check()`

```php
public function check(Agent $agent, string $instructions): array
```

---

## `CostProjection`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Simulation`
- **Source:** [src/Simulation/CostProjection.php](https://github.com/mosaiqo/proofread/blob/main/src/Simulation/CostProjection.php)

Projected cost for a single model computed from a set of shadow captures.

Covered captures are the ones that had usable token data and produced a
cost via the pricing table. Skipped captures either lacked tokens entirely
or the model was not present in the pricing table, so they do not
contribute to the total.

### Methods

#### `__construct()`

```php
public function __construct(string $model, float $totalCost, float $perCaptureCost, int $coveredCaptures, int $skippedCaptures)
```

### Public properties

- readonly `string $model`
- readonly `float $totalCost`
- readonly `float $perCaptureCost`
- readonly `int $coveredCaptures`
- readonly `int $skippedCaptures`

---

## `CostSimulationReport`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Simulation`
- **Source:** [src/Simulation/CostSimulationReport.php](https://github.com/mosaiqo/proofread/blob/main/src/Simulation/CostSimulationReport.php)

Aggregate cost-simulation report for one agent over a time window: the
current model's projected cost plus projections for alternative models.

### Methods

#### `__construct()`

```php
public function __construct(string $agentClass, CostProjection $current, array $projections, int $totalCaptures, DateTimeImmutable $from, DateTimeImmutable $to)
```

#### `cheapestAlternative()`

```php
public function cheapestAlternative(): ?CostProjection
```

#### `savingsVs()`

```php
public function savingsVs(string $model): ?float
```

#### `cheaperThanCurrent()`

```php
public function cheaperThanCurrent(): array
```

### Public properties

- readonly `string $agentClass`
- readonly `CostProjection $current`
- readonly `array $projections`
- readonly `int $totalCaptures`
- readonly `DateTimeImmutable $from`
- readonly `DateTimeImmutable $to`

---

## `CostSimulator`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Simulation`
- **Source:** [src/Simulation/CostSimulator.php](https://github.com/mosaiqo/proofread/blob/main/src/Simulation/CostSimulator.php)

Simulate the cost of running an agent's historical traffic under different
models. Takes the persisted ShadowCapture rows within a window, computes
the cost under the captures' actual model (the "current" projection) and
under a set of alternative models using the configured PricingTable.

The current model is chosen as the mode (most frequent model_used value)
across the matched captures. Ties are broken by the most recently captured
occurrence, which matches the intuition that a recent model migration is
the one to reason about when asking "what if I switch?".

Captures that are missing both tokens_in and tokens_out are considered
unusable and counted as skipped for every projection.

### Methods

#### `__construct()`

```php
public function __construct(PricingTable $pricing)
```

#### `simulate()`

```php
public function simulate(string $agentClass, DateTimeInterface $from, DateTimeInterface $to, array $alternativeModels = []): CostSimulationReport
```

---

## `CoverageAnalyzer`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Coverage`
- **Source:** [src/Coverage/CoverageAnalyzer.php](https://github.com/mosaiqo/proofread/blob/main/src/Coverage/CoverageAnalyzer.php)

Measure how well a dataset reflects production traffic by comparing the
embeddings of the dataset's case inputs against the embeddings of the
agent's recent shadow captures.

Each capture is attached to its nearest case by cosine similarity. If the
max similarity meets the threshold, the capture is "covered"; otherwise
it is an uncovered data point that suggests a gap the dataset should
grow to include. Uncovered captures are clustered so operators see the
patterns behind the gap instead of a flat list of stragglers.

Cost caveat: embedding N cases plus M captures costs tokens. With
OpenAI text-embedding-3-small at roughly $0.02 per 1M tokens this is
cheap for typical sizes, but not free. The maxCaptures cap prevents
accidentally running this against tens of thousands of captures.

### Methods

#### `__construct()`

```php
public function __construct(Similarity $similarity, FailureClusterer $clusterer)
```

#### `analyze()`

```php
public function analyze(string $agentClass, string $datasetName, DateTimeInterface $from, DateTimeInterface $to, float $threshold = 0.7, int $maxCaptures = 500, ?string $model = null): CoverageReport
```

---

## `CoverageReport`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Coverage`
- **Source:** [src/Coverage/CoverageReport.php](https://github.com/mosaiqo/proofread/blob/main/src/Coverage/CoverageReport.php)

Aggregate coverage report: for one agent, over a time window, it answers
"which dataset cases reflect production traffic and which captures have
no dataset analogue?".

### Methods

#### `__construct()`

```php
public function __construct(string $agentClass, string $datasetName, int $totalCaptures, int $coveredCount, int $uncoveredCount, int $skippedCount, float $threshold, array $caseCoverage, array $uncovered, array $uncoveredClusters, DateTimeImmutable $from, DateTimeImmutable $to)
```

#### `coverageRatio()`

```php
public function coverageRatio(): float
```

### Public properties

- readonly `string $agentClass`
- readonly `string $datasetName`
- readonly `int $totalCaptures`
- readonly `int $coveredCount`
- readonly `int $uncoveredCount`
- readonly `int $skippedCount`
- readonly `float $threshold`
- readonly `array $caseCoverage`
- readonly `array $uncovered`
- readonly `array $uncoveredClusters`
- readonly `DateTimeImmutable $from`
- readonly `DateTimeImmutable $to`

---

## `Dataset`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Support`
- **Source:** [src/Support/Dataset.php](https://github.com/mosaiqo/proofread/blob/main/src/Support/Dataset.php)

### Named constructors & static methods

#### `make()`

```php
public static function make(string $name, array $cases): self
```

### Methods

#### `count()`

```php
public function count(): int
```

#### `isEmpty()`

```php
public function isEmpty(): bool
```

### Public properties

- readonly `string $name`
- readonly `array $cases`

---

## `DatasetGenerator`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Generator`
- **Source:** [src/Generator/DatasetGenerator.php](https://github.com/mosaiqo/proofread/blob/main/src/Generator/DatasetGenerator.php)

### Methods

#### `__construct()`

```php
public function __construct(string $defaultModel, int $maxRetries = 1)
```

#### `defaultModel()`

```php
public function defaultModel(): string
```

#### `generate()`

```php
public function generate(string $criteria, array $schema, int $count, ?string $model = null, ?array $seedCases = null): array
```

Generate synthetic test cases using an LLM.

**Throws:** `\DatasetGeneratorException`

---

## `DatasetGeneratorAgent`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Generator`
- **Source:** [src/Generator/DatasetGeneratorAgent.php](https://github.com/mosaiqo/proofread/blob/main/src/Generator/DatasetGeneratorAgent.php)
- **Implements:** `Laravel\Ai\Contracts\Agent`

### Named constructors & static methods

#### `make()`

```php
public static function make(...$arguments): static
```

Create a new instance of the agent.

#### `fake()`

```php
public static function fake(Closure|array $responses = []): FakeTextGateway
```

Fake the responses returned by the agent.

#### `assertPrompted()`

```php
public static function assertPrompted(Closure|string $callback): void
```

Assert that a prompt was received matching a given truth test.

#### `assertNotPrompted()`

```php
public static function assertNotPrompted(Closure|string $callback): void
```

Assert that a prompt was not received matching a given truth test.

#### `assertNeverPrompted()`

```php
public static function assertNeverPrompted(): void
```

Assert that no prompts were received.

#### `assertQueued()`

```php
public static function assertQueued(Closure|string $callback): void
```

Assert that a queued prompt was received matching a given truth test.

#### `assertNotQueued()`

```php
public static function assertNotQueued(Closure|string $callback): void
```

Assert that a queued prompt was not received matching a given truth test.

#### `assertNeverQueued()`

```php
public static function assertNeverQueued(): void
```

Assert that no queued prompts were received.

#### `isFaked()`

```php
public static function isFaked(): bool
```

Determine if the agent is currently faked.

### Methods

#### `instructions()`

```php
public function instructions(): string
```

#### `prompt()`

```php
public function prompt(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): AgentResponse
```

Invoke the agent with a given prompt.

#### `stream()`

```php
public function stream(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null, ?int $timeout = null): StreamableAgentResponse
```

Invoke the agent with a given prompt and return a streamable response.

#### `queue()`

```php
public function queue(string $prompt, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): QueuedAgentResponse
```

Invoke the agent in a queued job.

#### `broadcast()`

```php
public function broadcast(string $prompt, Channel|array $channels, array $attachments = [], bool $now = false, Lab|array|string|null $provider = null, ?string $model = null): StreamableAgentResponse
```

Invoke the agent with a given prompt and broadcast the streamed events.

#### `broadcastNow()`

```php
public function broadcastNow(string $prompt, Channel|array $channels, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): StreamableAgentResponse
```

Invoke the agent with a given prompt and broadcast the streamed events immediately.

#### `broadcastOnQueue()`

```php
public function broadcastOnQueue(string $prompt, Channel|array $channels, array $attachments = [], Lab|array|string|null $provider = null, ?string $model = null): QueuedAgentResponse
```

Invoke the agent with a given prompt and broadcast the streamed events.

#### `restoreModel()`

```php
public function restoreModel($value)
```

Restore the model from the model identifier instance.

---

## `DatasetGeneratorException`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Generator`
- **Source:** [src/Generator/DatasetGeneratorException.php](https://github.com/mosaiqo/proofread/blob/main/src/Generator/DatasetGeneratorException.php)
- **Extends:** `RuntimeException`
- **Implements:** `Stringable`, `Throwable`

### Methods

#### `__construct()`

```php
public function __construct(string $message, string $lastRawResponse = '', int $attempts = 0)
```

### Public properties

- readonly `string $lastRawResponse`
- readonly `int $attempts`

---

## `EvalComparison`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Support`
- **Source:** [src/Support/EvalComparison.php](https://github.com/mosaiqo/proofread/blob/main/src/Support/EvalComparison.php)

### Named constructors & static methods

#### `make()`

```php
public static function make(string $name, Dataset $dataset, array $runs, float $durationMs): self
```

### Methods

#### `passed()`

```php
public function passed(): bool
```

#### `subjectLabels()`

```php
public function subjectLabels(): array
```

#### `runForSubject()`

```php
public function runForSubject(string $label): ?EvalRun
```

#### `passRates()`

```php
public function passRates(): array
```

#### `totalCosts()`

```php
public function totalCosts(): array
```

### Public properties

- readonly `string $name`
- readonly `Dataset $dataset`
- readonly `array $runs`
- readonly `float $durationMs`

---

## `EvalResult`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Support`
- **Source:** [src/Support/EvalResult.php](https://github.com/mosaiqo/proofread/blob/main/src/Support/EvalResult.php)

### Named constructors & static methods

#### `make()`

```php
public static function make(array $case, mixed $output, array $assertionResults, float $durationMs, ?Throwable $error = null): self
```

### Methods

#### `passed()`

```php
public function passed(): bool
```

#### `failed()`

```php
public function failed(): bool
```

#### `hasError()`

```php
public function hasError(): bool
```

### Public properties

- readonly `array $case`
- readonly `mixed $output`
- readonly `array $assertionResults`
- readonly `float $durationMs`
- readonly `?Throwable $error`

---

## `EvalRun`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Support`
- **Source:** [src/Support/EvalRun.php](https://github.com/mosaiqo/proofread/blob/main/src/Support/EvalRun.php)

### Named constructors & static methods

#### `make()`

```php
public static function make(Dataset $dataset, array $results, float $durationMs): self
```

### Methods

#### `passed()`

```php
public function passed(): bool
```

#### `failed()`

```php
public function failed(): bool
```

#### `passedCount()`

```php
public function passedCount(): int
```

#### `failedCount()`

```php
public function failedCount(): int
```

#### `total()`

```php
public function total(): int
```

#### `passRate()`

```php
public function passRate(): float
```

#### `failures()`

```php
public function failures(): array
```

#### `saveJUnitTo()`

```php
public function saveJUnitTo(string $path): void
```

#### `toJUnitXml()`

```php
public function toJUnitXml(): string
```

### Public properties

- readonly `Dataset $dataset`
- readonly `array $results`
- readonly `float $durationMs`

---

## `EvalRunDelta`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Diff`
- **Source:** [src/Diff/EvalRunDelta.php](https://github.com/mosaiqo/proofread/blob/main/src/Diff/EvalRunDelta.php)

### Methods

#### `__construct()`

```php
public function __construct(string $baseRunId, string $headRunId, string $datasetName, int $totalCases, int $regressions, int $improvements, int $stableFailures, int $stablePasses, float $costDeltaUsd, float $durationDeltaMs, array $cases)
```

#### `hasRegressions()`

```php
public function hasRegressions(): bool
```

#### `toArray()`

```php
public function toArray(): array
```

Canonical JSON-friendly array shape shared by the MCP tool,
evals:compare CLI, and regression webhook generic payload.

### Public properties

- readonly `string $baseRunId`
- readonly `string $headRunId`
- readonly `string $datasetName`
- readonly `int $totalCases`
- readonly `int $regressions`
- readonly `int $improvements`
- readonly `int $stableFailures`
- readonly `int $stablePasses`
- readonly `float $costDeltaUsd`
- readonly `float $durationDeltaMs`
- readonly `array $cases`

---

## `EvalRunDiff`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Diff`
- **Source:** [src/Diff/EvalRunDiff.php](https://github.com/mosaiqo/proofread/blob/main/src/Diff/EvalRunDiff.php)

Computes a structured diff between two persisted EvalRun models.

Both runs must target the same dataset (matched by name). The returned
EvalRunDelta classifies each case as regression, improvement, stable pass,
stable fail, base-only, or head-only, and aggregates cost and duration
differences across the run.

### Methods

#### `compute()`

```php
public function compute(EvalRun $base, EvalRun $head): EvalRunDelta
```

---

## `FailureClusterer`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Clustering`
- **Source:** [src/Clustering/FailureClusterer.php](https://github.com/mosaiqo/proofread/blob/main/src/Clustering/FailureClusterer.php)

Group failure signals into clusters of semantically similar items using
threshold-based single-pass clustering over embedding cosine similarity.

The algorithm scans signals in their original order. For each signal it
compares against the representative of every existing cluster: if the
similarity meets the threshold, the signal joins that cluster; otherwise
a new cluster is seeded. Deterministic, linear in |signals| * |clusters|,
and does not require a target cluster count.

### Methods

#### `__construct()`

```php
public function __construct(Similarity $similarity)
```

#### `cluster()`

```php
public function cluster(array $signals, float $threshold = 0.75, ?string $model = null): array
```

Cluster failure signals using embedding-based cosine similarity.

---

## `JudgeResult`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Support`
- **Source:** [src/Support/JudgeResult.php](https://github.com/mosaiqo/proofread/blob/main/src/Support/JudgeResult.php)
- **Extends:** `Mosaiqo\Proofread\Support\AssertionResult`

### Named constructors & static methods

#### `pass()`

```php
public static function pass(string $reason = '', ?float $score = null, array $metadata = [], string $judgeModel = '', int $retryCount = 0): self
```

Build a passing judge result. Note: parameter order follows
AssertionResult::pass() for LSP compliance, with judge-specific
fields appended after metadata.

#### `fail()`

```php
public static function fail(string $reason, ?float $score = null, array $metadata = [], string $judgeModel = '', int $retryCount = 0): self
```

Build a failing judge result. Score may be null (e.g., when the judge
itself errored out and never produced a score).

### Public properties

- readonly `string $judgeModel`
- readonly `int $retryCount`

---

## `LengthRule`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint\Rules`
- **Source:** [src/Lint/Rules/LengthRule.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/Rules/LengthRule.php)
- **Implements:** `Mosaiqo\Proofread\Lint\Contracts\LintRule`

### Methods

#### `name()`

```php
public function name(): string
```

#### `check()`

```php
public function check(Agent $agent, string $instructions): array
```

---

## `LintIssue`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint`
- **Source:** [src/Lint/LintIssue.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/LintIssue.php)

### Named constructors & static methods

#### `error()`

```php
public static function error(string $ruleName, string $message, ?string $suggestion = null, ?int $lineNumber = null): self
```

#### `warning()`

```php
public static function warning(string $ruleName, string $message, ?string $suggestion = null, ?int $lineNumber = null): self
```

#### `info()`

```php
public static function info(string $ruleName, string $message, ?string $suggestion = null, ?int $lineNumber = null): self
```

### Methods

#### `__construct()`

```php
public function __construct(string $ruleName, string $severity, string $message, ?string $suggestion = null, ?int $lineNumber = null)
```

#### `toArray()`

```php
public function toArray(): array
```

### Public properties

- readonly `string $ruleName`
- readonly `string $severity`
- readonly `string $message`
- readonly `?string $suggestion`
- readonly `?int $lineNumber`

---

## `LintReport`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint`
- **Source:** [src/Lint/LintReport.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/LintReport.php)

### Methods

#### `__construct()`

```php
public function __construct(string $agentClass, string $instructions, array $issues)
```

#### `hasErrors()`

```php
public function hasErrors(): bool
```

#### `hasIssues()`

```php
public function hasIssues(): bool
```

#### `issuesWithSeverity()`

```php
public function issuesWithSeverity(string $severity): array
```

#### `errorCount()`

```php
public function errorCount(): int
```

#### `warningCount()`

```php
public function warningCount(): int
```

#### `infoCount()`

```php
public function infoCount(): int
```

#### `toArray()`

```php
public function toArray(): array
```

### Public properties

- readonly `string $agentClass`
- readonly `string $instructions`
- readonly `array $issues`

---

## `LintRule`

- **Kind:** Interface
- **Namespace:** `Mosaiqo\Proofread\Lint\Contracts`
- **Source:** [src/Lint/Contracts/LintRule.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/Contracts/LintRule.php)

### Methods

#### `name()`

```php
public function name(): string
```

#### `check()`

```php
public function check(Agent $agent, string $instructions): array
```

---

## `MissingOutputFormatRule`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint\Rules`
- **Source:** [src/Lint/Rules/MissingOutputFormatRule.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/Rules/MissingOutputFormatRule.php)
- **Implements:** `Mosaiqo\Proofread\Lint\Contracts\LintRule`

### Methods

#### `name()`

```php
public function name(): string
```

#### `check()`

```php
public function check(Agent $agent, string $instructions): array
```

---

## `MissingRoleRule`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint\Rules`
- **Source:** [src/Lint/Rules/MissingRoleRule.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/Rules/MissingRoleRule.php)
- **Implements:** `Mosaiqo\Proofread\Lint\Contracts\LintRule`

### Methods

#### `name()`

```php
public function name(): string
```

#### `check()`

```php
public function check(Agent $agent, string $instructions): array
```

---

## `PromptLinter`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint`
- **Source:** [src/Lint/PromptLinter.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/PromptLinter.php)

### Methods

#### `__construct()`

```php
public function __construct(array $rules)
```

#### `rules()`

```php
public function rules(): array
```

#### `lintClass()`

```php
public function lintClass(string $agentClass): LintReport
```

---

## `RunResolver`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Support`
- **Source:** [src/Support/RunResolver.php](https://github.com/mosaiqo/proofread/blob/main/src/Support/RunResolver.php)

Resolve a textual reference into a persisted EvalRun model.

Accepted reference forms:
- Full ULID (26 chars) — matched exactly against the run id.
- Commit SHA prefix (4-40 hex chars) — matched via prefix against
  the commit_sha column, most recent first.
- Literal "latest" — the most recently created run in the database.

### Methods

#### `resolve()`

```php
public function resolve(string $identifier): ?EvalRun
```

---

## `SemanticQualityRule`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Lint\Rules`
- **Source:** [src/Lint/Rules/SemanticQualityRule.php](https://github.com/mosaiqo/proofread/blob/main/src/Lint/Rules/SemanticQualityRule.php)
- **Implements:** `Mosaiqo\Proofread\Lint\Contracts\LintRule`

LLM-based semantic analysis of an Agent's instructions.

Calls the Judge agent with a custom prompt that asks for a structured
critique (passed, score, reason, issues[]) and converts the response
into LintIssues. Designed to be opt-in via the lint command's flag.

### Methods

#### `name()`

```php
public function name(): string
```

#### `check()`

```php
public function check(Agent $agent, string $instructions): array
```

---

## `UncoveredCapture`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Coverage`
- **Source:** [src/Coverage/UncoveredCapture.php](https://github.com/mosaiqo/proofread/blob/main/src/Coverage/UncoveredCapture.php)

A shadow capture that is not covered by any dataset case: its max cosine
similarity against every case is below the configured threshold.

### Methods

#### `__construct()`

```php
public function __construct(string $captureId, string $inputSnippet, float $maxSimilarity, int $nearestCaseIndex)
```

### Public properties

- readonly `string $captureId`
- readonly `string $inputSnippet`
- readonly `float $maxSimilarity`
- readonly `int $nearestCaseIndex`

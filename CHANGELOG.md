# Changelog

All notable changes to `mosaiqo/proofread` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.1] - 2026-04-17

### Added

- `CountAssertion` for validating array or Countable sizes, with `equals()`,
  `atLeast()`, `atMost()`, and `between()` named constructors.

### Fixed

- `JsonSchemaAssertion` now preserves PHP lists during normalization,
  allowing schemas with `type: array` (including nested empty arrays) to
  validate correctly. Previously, all arrays — including empty lists —
  were coerced to `stdClass`, causing false failures against list-typed
  schemas.
- `ProofreadServiceProvider` now discovers migrations automatically
  instead of registering each by name. Shadow migrations
  (`create_shadow_captures_table`, `create_shadow_evals_table`) were
  silently missed by the previous name-based registration, leaving
  shadow-eval tables uncreated in fresh installs.

### Changed

- `Rubric` class-level docblock documents the `JudgeAgent::fake(...)`
  pattern for testing assertions that invoke an LLM judge.
- `EvalSuite::subject()` docblock explains the supported subject shapes
  (callable, Agent class-string FQCN, Agent instance) and the callable
  signature `fn (mixed $input, array $case): mixed`, with a multi-input
  unwrap example.

## [0.1.0] - 2026-04-16

Initial public release. Proofread is the first eval package native to the
official Laravel AI stack: agent classes as test subjects, Pest-native
expectations, and shadow evals on production traffic.

### Added

#### Core runtime

- `EvalRunner` orchestrates evals over a dataset with support for callable,
  Agent FQCN, and Agent instance subjects.
- `SubjectResolver` normalizes all supported subject shapes into a uniform
  closure, resolving Agent FQCNs lazily from the container.
- `SubjectInvocation` carries per-case output plus metadata (tokens, model,
  provider, latency, derived cost) into assertion context.
- `Dataset`, `EvalResult`, and `EvalRun` immutable value objects.
- `AssertionResult` value object with `passed`, `reason`, optional `score`,
  and arbitrary `metadata`.
- `Assertion` and `Runner` contracts under `Mosaiqo\Proofread\Contracts`.
- `EvalSuite` abstract class with `dataset()`, `subject()`, and
  `assertions()` contract methods.
- `EvalRun::toJUnitXml()` serialization plus `Proofread::writeJUnit()` atomic
  writer for CI reporters.

#### Assertions

- Deterministic: `ContainsAssertion`, `RegexAssertion`, `LengthAssertion`,
  `JsonSchemaAssertion` (including `fromAgent()` for `HasStructuredOutput`
  agents, `fromArray()`, `fromJson()`, `fromFile()`).
- Operational: `TokenBudget` (`maxInput`, `maxOutput`, `maxTotal`),
  `CostLimit` (`under`), `LatencyLimit` (`under`).
- Semantic: `Rubric` (LLM-as-judge, powered by the `Judge` service),
  `Similar` (cosine similarity via the `Similarity` service).
- Trajectory: `Trajectory` with step-count and tool-call checks
  (`maxSteps`, `minSteps`, `stepsBetween`, `callsTool`, `doesNotCallTool`,
  `callsTools`, `callsToolsInOrder`).
- Snapshot: `GoldenSnapshot` (`forKey`, `fromContext`) backed by a
  filesystem `SnapshotStore` with update mode via env flag.

#### Pest expectations

- `toPassAssertion(Assertion $assertion)` — runs any assertion against the
  current expectation value.
- `toPassEval(Dataset $dataset, array $assertions)` — full eval run against
  a callable or Agent subject.
- `toPassRubric(string $criteria, array $options)` — LLM-as-judge rubric
  expectation with optional model and min-score overrides.
- `toMatchSchema(array|string $schema)` — JSON schema conformance from array,
  JSON string, or file path.
- `toCostUnder(float $maxUsd)` — asserts total cost across an `EvalRun`.
- `toMatchGoldenSnapshot(?string $key)` — snapshot expectation with key
  derivation from the current Pest test context.

#### Persistence and Artisan

- Migrations for `eval_datasets`, `eval_runs`, `eval_results`,
  `shadow_captures`, and `shadow_evals` tables.
- Eloquent models mirroring the persisted tables.
- `EvalPersister` service and `--persist` flag on `evals:run`.
- `evals:run` Artisan command with `--persist`, `--junit`, and `--queue`
  flags.
- `evals:compare {base} {head}` command backed by the `EvalRunDiff`
  service, reporting regressions, improvements, and cost or duration
  deltas.
- `evals:cluster` command backed by `FailureClusterer`, grouping failing
  eval results or shadow evals by embedding similarity.
- `dataset:generate` command backed by the `DatasetGenerator` service for
  synthesizing cases from a schema via an LLM.
- `shadow:evaluate` and `shadow:alert` commands (see Shadow evals below).
- `RunEvalSuiteJob` for async eval execution via `--queue`.

#### Dashboard

- Livewire-based dashboard mounted under a configurable path (defaults to
  `/evals`).
- `Overview` with trend chart and recent regressions.
- `RunsList` with filters, status pills, and stats.
- `RunDetail` with per-case drill-down drawer.
- `DatasetsList` explorer with per-dataset sparklines.
- `CompareRuns` side-by-side diff view.
- `CostsBreakdown` by model and by dataset.
- `ShadowPanel` with captures, evals, and promote-to-dataset flow.
- `viewEvals` Gate with a default allowing the `local` environment only,
  intended to be overridden by the host application.

#### Shadow evals

- `EvalShadowMiddleware` for capturing agent traffic with configurable
  sample rate and deterministic sampling via an injectable
  `RandomNumberProvider`.
- `PiiSanitizer` for redacting sensitive fields and patterns before
  persistence.
- `ShadowAssertionsRegistry` for per-agent assertion registration.
- `ShadowEvaluator` running registered assertions over captures.
- `ShadowAlertService`, `ShadowAlert` value object, and
  `ShadowPassRateDroppedNotification` for mail and Slack alerts on pass-rate
  regressions over a sliding window.
- `EvalRunRegressed` event plus `CheckForRegressionListener` for automatic
  detection, and `NotifyWebhookOnRegression` listener for Slack, Discord,
  and generic JSON webhooks.

#### MCP integration

- `Mosaiqo\Proofread\Mcp\McpIntegration` helper exposing three tools,
  guarded by `class_exists()` so the core package works without
  `laravel/mcp`.
- `ListEvalSuitesTool` — lists registered suites from config.
- `RunEvalSuiteTool` — runs a suite and returns the structured result.
- `GetEvalRunDiffTool` — diffs two persisted runs by identifier.

#### Developer ergonomics

- `PricingTable` service covering input, output, cache read, cache write,
  and reasoning tokens for Claude 4.x, GPT-4o, o1 series, Gemini 1.5, and
  common OpenAI embedding models. Overridable per-model in config.
- `Judge` service with JSON response parsing, retries, and `JudgeResult`
  subclass carrying the judge model, score, reason, and retry count.
- `Similarity` service with a reusable `cosineFromVectors` helper.
- PHPStan extension teaching static analysis about Proofread's dynamic
  Pest expectations — no stub files to maintain.
- Example `ExampleAgent`, `SentimentEvalSuite`, and `example-dataset.php`
  shipped under `examples/`.
- Package scaffold built on `spatie/laravel-package-tools`, Pest v4,
  Orchestra Testbench v11, PHPStan, and GitHub Actions CI.

[Unreleased]: https://github.com/mosaiqo/proofread/compare/v0.1.1...HEAD
[0.1.1]: https://github.com/mosaiqo/proofread/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/mosaiqo/proofread/releases/tag/v0.1.0

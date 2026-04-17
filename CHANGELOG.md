# Changelog

All notable changes to `mosaiqo/proofread` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.0] - 2026-04-17

### Added

- Multi-provider comparison subsystem. Build a
  `MultiSubjectEvalSuite` declaring a map of `label => subject` and run
  the same dataset against every subject in a single invocation.
- `MultiSubjectEvalSuite` abstract class extending `EvalSuite`.
  `subjects(): array<string, mixed>` returns the map; `subject()` is
  final and returns the first subject for backward compatibility with
  single-subject runners.
- `EvalComparison` Eloquent model + schema (`eval_comparisons` table,
  nullable `comparison_id` and `subject_label` columns on `eval_runs`).
  Comparisons link to their dataset version and persist aggregate
  stats (total_runs, passed_runs, failed_runs, total_cost_usd, duration).
- `Support\EvalComparison` immutable value object — the in-memory
  result of a comparison run. Exposes `passed()`, `passRates()`,
  `totalCosts()`, `runForSubject($label)`.
- `ComparisonRunner` service that orchestrates running a
  `MultiSubjectEvalSuite` against each subject. `setUp`/`tearDown`
  invoked once per comparison; subjects can run sequentially or in
  parallel via the existing `ConcurrencyDriver` (provider-level
  parallelism). Inner case concurrency is orthogonal and still
  available.
- `ComparisonPersister` service — persists a `Support\EvalComparison`
  into an `EvalComparison` Eloquent model plus N `EvalRun` rows linked
  via `comparison_id` and `subject_label`. Transactional.
- `evals:providers {suite}` Artisan command. Flags: `--persist`,
  `--commit-sha`, `--concurrency` (cases per run), `--provider-concurrency`
  (subjects in parallel), `--fake-judge`, `--format=table|json`.
  Renders a matrix of cases × subjects with pass/fail badges plus
  per-subject aggregate rows (pass rate, cost, avg latency). Exit
  codes: 0 all pass, 1 any subject fails, 2 args/class error.
- `evals:export {id}` now also exports comparisons. Auto-detects the
  identifier type (run vs comparison) by ULID uniqueness, with
  `--type=run|comparison` as an explicit override for edge cases.
  Comparisons render as Markdown or HTML documents including header,
  summary, winners (best pass rate / cheapest / fastest), a cases ×
  subjects matrix, and per-subject stats sections.
- `ComparisonResolver` helper — resolves a comparison by full ULID,
  ULID prefix, commit SHA prefix (4-40 hex), or the `latest` keyword.
  Mirrors `RunResolver`.
- `/evals/comparisons` Livewire list view. Filters (dataset, status,
  search), paginated, stat cards (total comparisons, 7-day pass rate,
  active datasets compared), subject pills with overflow indicator.
- `/evals/comparisons/{comparison}` Livewire matrix detail. Header
  with metadata, three winner cards
  (best pass rate / cheapest / fastest), matrix cell grid with
  click-to-drill drawer showing input/output/assertions/metadata/error,
  and a link from each drawer to the full `EvalRun` of the subject.
- `JudgeFaker` shared helper — extracts the `--fake-judge` logic from
  `RunEvalsCommand` so `evals:providers` reuses the same `pass|fail|path`
  semantics without duplicating code.
- `EvalRunner::runSuiteForSubject()` — internal method used by
  `ComparisonRunner` to invoke the runner for a single subject while
  injecting `subject_label` into each case's context. Marked
  `@internal`; public APIs are unchanged.

### Changed

- `EvalPersister::persist()` accepts two new optional parameters:
  `?string $comparisonId` and `?string $subjectLabel`. They are written
  onto the created `EvalRun` row when provided. Existing callers do not
  need to change — the parameters default to null and the stored
  behavior is identical.
- Dashboard navigation includes a "Comparisons" link.

## [0.3.0] - 2026-04-17

### Added

- `EvalDatasetVersion` model and schema capturing per-checksum snapshots
  of a dataset's cases. Every persisted `EvalRun` links to the version
  it was evaluated against, enabling accurate historical analysis when
  datasets evolve.
- `evals:dataset:diff {dataset}` Artisan command that compares two
  versions of a dataset side by side. Resolves versions by short
  checksum prefix, run ULID, or the keywords `latest` / `previous`.
  Reports cases added, removed, modified, or unchanged in either
  `table` or `json` format.
- `evals:export {run}` Artisan command that exports a persisted run
  as a self-contained Markdown or HTML document. Suitable for PR
  descriptions, CI artifacts, or sharing with stakeholders without
  dashboard access.
- `RunResolver` helper that resolves a run identifier (ULID, short
  commit SHA, or `latest` keyword) to an `EvalRun` model. Used by the
  new export command and available for external reuse.
- `Proofread::writeFile(string $path, string $contents): void` —
  generic atomic file writer (temp file + rename). `writeJUnit()` now
  delegates to it.
- `Proofread::VERSION` class constant for programmatic version checks
  and version headers in generated exports.
- `ConcurrencyDriver` contract and two implementations:
  `LaravelConcurrencyDriver` (production, wraps Laravel's
  `Illuminate\Support\Facades\Concurrency` process driver) and
  `SyncConcurrencyDriver` (test-friendly, runs tasks inline).
- `EvalRunner::runSuite($suite, concurrency: N)` and
  `EvalRunner::run(..., concurrency: N)` — optional parallelism for
  I/O-bound suites. Default `concurrency: 1` preserves existing
  sequential semantics. Cases are chunked into batches of size
  `concurrency` and each batch runs concurrently via the driver.
- `evals:run --concurrency=N` CLI flag to enable parallel case
  execution from the command line.

### Changed

- `evals:run` pre-run header now reports per-case assertion counts as
  a range when they vary across cases (e.g. `3-5 assertions per case`)
  instead of quoting the fixed `assertions()` count. Suites whose
  per-case count is constant still see the singular form
  (`3 assertions per case`).
- Legacy `create_eval_*_table.php` migrations renamed with
  `2026_04_01_000001..000003` date prefixes so they sort consistently
  alongside new migrations. Pre-v1 consumers who previously relied on
  `discoversMigrations` auto-loading should republish their
  migrations after upgrading.
- `EvalPersister::persist()` now creates or reuses an
  `EvalDatasetVersion` row on every persist, writing the full
  `cases` snapshot for new versions. External behavior is otherwise
  unchanged; existing `EvalRun` rows before v0.3 remain valid with
  `dataset_version_id` null.

## [0.2.0] - 2026-04-17

### Added

- `EvalSuite::setUp()` and `EvalSuite::tearDown()` lifecycle hooks.
  Override these to seed database state, initialize tenant context,
  or prepare fixtures before a suite runs. `tearDown()` is called
  inside a `finally` block so it runs even when the subject or an
  assertion throws.
- `EvalRunner::runSuite(EvalSuite $suite): EvalRun` method that
  orchestrates a suite's full lifecycle (setUp, read dataset/subject/
  assertions, run, tearDown). Existing callers — `evals:run`, the
  `RunEvalSuiteJob` queue job, and the MCP `run_eval_suite` tool — now
  route through this method, so every suite benefits from the lifecycle
  hooks automatically.
- `EvalSuite::assertionsFor(array $case): array` method. Override to
  compose assertions that depend on per-case metadata
  (e.g. `$case['meta']['expected_count']`). Defaults to delegating to
  `assertions()`, so existing suites behave identically without changes.
- `toPassSuite()` Pest expectation that accepts an `EvalSuite` and
  runs it through the full lifecycle. Failure messages list up to
  three failing cases with assertion names and reasons, mirroring
  `toPassEval`'s output format.
- `evals:run --fake-judge=SPEC` flag for running Rubric-enabled suites
  from the CLI without hitting a real LLM. `SPEC` can be `pass`, `fail`,
  or a path to a JSON file containing per-invocation judge responses.

### Changed

- `RunEvalsCommand`, `RunEvalSuiteJob`, and the MCP `run_eval_suite`
  tool now all use `EvalRunner::runSuite()` under the hood. External
  behavior is unchanged, but suites with `setUp`, `tearDown`, or
  `assertionsFor` overrides now get full lifecycle support from every
  entry point.

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

[Unreleased]: https://github.com/mosaiqo/proofread/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/mosaiqo/proofread/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/mosaiqo/proofread/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/mosaiqo/proofread/compare/v0.1.1...v0.2.0
[0.1.1]: https://github.com/mosaiqo/proofread/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/mosaiqo/proofread/releases/tag/v0.1.0

---
title: "Artisan commands"
section: "API Reference"
---

# Artisan commands

> **[info]** This page is auto-generated from PHP docblocks.
> Regenerate with `composer docs:api` after editing any docblock.

Artisan commands exposed by Proofread. Invoke via `php artisan <signature>`.

## Summary

| Class | Kind | Description |
|---|---|---|
| [`BenchmarkEvalsCommand`](#benchmarkevalscommand) | Final class | Run a suite multiple times and report stability statistics. |
| [`ClusterFailuresCommand`](#clusterfailurescommand) | Final class | Group recent failures by semantic similarity so operators can spot systemic patterns instead of reading failures case-by-case. |
| [`CompareEvalsCommand`](#compareevalscommand) | Final class | Compare two persisted eval runs of the same dataset and report the diff. |
| [`CoverageCommand`](#coveragecommand) | Final class | Surface which production captures the dataset does not cover. Embed every dataset case and every recent capture, match each capture to its nearest case by cosine similarity, report the gap, and cluster the uncovered captures so the next dataset revision has obvious targets. |
| [`DatasetDiffCommand`](#datasetdiffcommand) | Final class | Diff two versions of a dataset. |
| [`ExportDatasetCommand`](#exportdatasetcommand) | Final class | Export a persisted dataset version as JSON or CSV. |
| [`ExportRunCommand`](#exportruncommand) | Final class | Export a persisted eval run or comparison as a self-contained Markdown or HTML document. |
| [`GenerateDatasetCommand`](#generatedatasetcommand) | Final class | — |
| [`ImportDatasetCommand`](#importdatasetcommand) | Final class | Import a CSV or JSON file into a PHP dataset file usable by Proofread. |
| [`LintCommand`](#lintcommand) | Final class | — |
| [`ProofreadMakeAssertionCommand`](#proofreadmakeassertioncommand) | Final class | — |
| [`ProofreadMakeDatasetCommand`](#proofreadmakedatasetcommand) | Final class | — |
| [`ProofreadMakeSuiteCommand`](#proofreadmakesuitecommand) | Final class | — |
| [`RunEvalsCommand`](#runevalscommand) | Final class | — |
| [`RunProviderComparisonCommand`](#runprovidercomparisoncommand) | Final class | — |
| [`ShadowAlertCommand`](#shadowalertcommand) | Final class | Evaluate the rolling shadow pass rate for one or all agents and dispatch a ShadowPassRateDroppedNotification for each agent whose pass rate has dropped below the configured threshold. Alerts are deduped via the cache so an agent below threshold does not page on every scheduled run. |
| [`ShadowEvaluateCommand`](#shadowevaluatecommand) | Final class | — |
| [`SimulateCostCommand`](#simulatecostcommand) | Final class | Simulate what an agent's historical production traffic would have cost under alternative models. Analyzes shadow captures inside a time window and produces a side-by-side cost comparison to help decide whether switching models is worth it. |

---

## `BenchmarkEvalsCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/BenchmarkEvalsCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/BenchmarkEvalsCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Run a suite multiple times and report stability statistics.

Unlike `evals:run`, this command treats the suite as a fixed benchmark
and measures how reliably it passes across N iterations. Useful when the
underlying subject is non-deterministic (LLMs) and you want to quantify
flakiness and pricing variance before trusting a pass/fail signal from
a single run.

Exit codes:
- 0 → every case is stable (pass ratio >= threshold across iterations).
- 1 → one or more cases fall below the flakiness threshold.
- 2 → argument/resolution error.

### Artisan signature

```bash
php artisan evals:benchmark {suite : FQCN of the EvalSuite subclass to benchmark} {--iterations=10 : Number of iterations (>= 2)} {--concurrency=1 : Cases per iteration run in parallel} {--fake-judge= : Fake the judge agent: "pass", "fail", or a JSON path} {--flakiness-threshold=0.8 : Minimum per-case pass ratio to consider stable} {--format=table : Output format: table or json}
```

> Run a suite N times and report pass-rate variance, duration percentiles, cost, and flakiness.

### Methods

#### `handle()`

```php
public function handle(EvalRunner $runner): int
```

---

## `ClusterFailuresCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/ClusterFailuresCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/ClusterFailuresCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Group recent failures by semantic similarity so operators can spot systemic
patterns instead of reading failures case-by-case.

### Artisan signature

```bash
php artisan evals:cluster {--source=eval_results : Source of failures: eval_results or shadow_evals} {--dataset= : Filter by dataset name (eval_results only)} {--agent= : Filter by agent FQCN (shadow_evals only)} {--since= : Only include failures newer than this duration (e.g. 1h, 24h, 7d)} {--threshold= : Minimum cosine similarity to join a cluster (default from config)} {--limit= : Maximum number of failures to process (default from config)} {--format=table : Output format: table or json}
```

> Group failing eval results or shadow evals by semantic similarity.

### Methods

#### `handle()`

```php
public function handle(FailureClusterer $clusterer): int
```

---

## `CompareEvalsCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/CompareEvalsCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/CompareEvalsCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Compare two persisted eval runs of the same dataset and report the diff.

Accepted reference forms for the base/head arguments:
  - Full ULID (26 chars) - matched exactly.
  - Short or full commit SHA (7-40 hex chars) - matched via prefix
    against the commit_sha column, most recent first.
  - Literal "latest" - the most recently created run in the DB.

Exit codes follow CI conventions: 0 when there are no regressions,
1 when regressions are detected, 2 on argument/resolution errors.

### Artisan signature

```bash
php artisan evals:compare {base : Base run reference (ULID, commit SHA, or "latest")} {head : Head run reference (ULID, commit SHA, or "latest")} {--format=table : Output format: table, json, or markdown} {--only-regressions : Only show regression cases in the table output} {--max-cases=50 : Maximum number of cases to render in the table output} {--output= : Write the formatted diff to this file path instead of stdout}
```

> Compare two persisted eval runs and report regressions, improvements, and cost/duration deltas.

### Methods

#### `handle()`

```php
public function handle(EvalRunDiff $diff): int
```

---

## `CoverageCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/CoverageCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/CoverageCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Surface which production captures the dataset does not cover. Embed
every dataset case and every recent capture, match each capture to its
nearest case by cosine similarity, report the gap, and cluster the
uncovered captures so the next dataset revision has obvious targets.

### Artisan signature

```bash
php artisan evals:coverage {agent : FQCN of an Agent to analyze} {dataset : Name of the EvalDataset to compare against} {--days=30 : Number of days back from now to include} {--threshold=0.7 : Minimum cosine similarity to consider a capture covered} {--max-captures=500 : Maximum number of captures to analyze} {--embedding-model= : Override the embedding model} {--format=table : Output format: table or json}
```

> Analyze which shadow captures are not covered by a dataset using embedding similarity.

### Methods

#### `handle()`

```php
public function handle(CoverageAnalyzer $analyzer): int
```

---

## `DatasetDiffCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/DatasetDiffCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/DatasetDiffCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Diff two versions of a dataset.

Accepted reference forms for the --base / --head options:
  - Short checksum (>= 6 hex chars) matched as prefix on the version checksum.
  - Run ULID (26 chars) — resolves to that run's dataset_version_id.
  - Literal "latest" — most recent version by first_seen_at.
  - Literal "previous" or "latest-1" — second-most-recent version by first_seen_at.

Cases are indexed by meta.name when present, otherwise by positional
case_index. The comparison compares input + expected + meta (with the
indexing field stripped from meta when it was meta.name).

### Artisan signature

```bash
php artisan evals:dataset:diff {dataset_name : Name of the dataset to diff} {--base= : Base version reference (short checksum, run ULID, "latest", "previous" / "latest-1")} {--head= : Head version reference (short checksum, run ULID, "latest", "previous" / "latest-1")} {--format=table : Output format: table or json}
```

> Diff two dataset versions to see how cases were added, removed, or modified.

### Methods

#### `handle()`

```php
public function handle(): int
```

---

## `ExportDatasetCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/ExportDatasetCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/ExportDatasetCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Export a persisted dataset version as JSON or CSV.

Version resolution (in order):
- `--version=latest` (default) → most recent version by first_seen_at.
- `--version=<short-checksum>` → prefix match (>= 6 hex chars) against
  `eval_dataset_versions.checksum`.

### Artisan signature

```bash
php artisan dataset:export {dataset : Name of the dataset to export} {--format=csv : Output format: csv or json} {--output= : Write the export to this path instead of stdout} {--dataset-version=latest : Version identifier (checksum prefix or "latest")}
```

> Export a persisted dataset version as JSON or CSV.

### Methods

#### `handle()`

```php
public function handle(): int
```

---

## `ExportRunCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/ExportRunCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/ExportRunCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Export a persisted eval run or comparison as a self-contained Markdown
or HTML document.

Accepted reference forms for the {run} argument:
  - Full ULID (26 chars).
  - Commit SHA prefix (4-40 hex chars).
  - Literal "latest".

Use --type to disambiguate between runs and comparisons when the
identifier could match both.

### Artisan signature

```bash
php artisan evals:export {run : Run or comparison reference (ULID, commit SHA, or "latest")} {--format=md : Output format: md or html} {--output= : Write the export to this path instead of stdout} {--type=auto : Subject type: auto, run, or comparison}
```

> Export a persisted eval run or comparison as a shareable Markdown or HTML document.

### Methods

#### `handle()`

```php
public function handle(RunResolver $runResolver, ComparisonResolver $comparisonResolver, EvalRunExporter $runExporter, EvalComparisonExporter $comparisonExporter): int
```

---

## `GenerateDatasetCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/GenerateDatasetCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/GenerateDatasetCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

### Artisan signature

```bash
php artisan dataset:generate {--agent= : FQCN of an Agent implementing HasStructuredOutput to derive the schema from} {--schema= : Path to a JSON Schema file (mutually exclusive with --agent)} {--criteria= : Description of the dataset purpose (required)} {--count=10 : Number of cases to generate (1-100)} {--seed= : Path to a PHP file returning an array of seed cases (optional)} {--output= : Destination path; when set, writes to file (appends if it exists)} {--format=php : Output format: php or json} {--model= : Override the generator model}
```

> Generate a synthetic dataset using an LLM.

### Methods

#### `handle()`

```php
public function handle(DatasetGenerator $generator): int
```

---

## `ImportDatasetCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/ImportDatasetCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/ImportDatasetCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Import a CSV or JSON file into a PHP dataset file usable by Proofread.

CSV limitations:
- Cells are parsed as scalar strings; complex values (arrays, nested
  structures) cannot be expressed. Use JSON for those shapes.
- Meta keys are encoded via `meta_*` columns and flattened into a
  one-level `meta` array.

JSON format:
- Top-level array of case objects.
- Each case must contain `input`. `expected` and `meta` are optional
  and can be arbitrarily nested.

### Artisan signature

```bash
php artisan dataset:import {file : Path to a CSV or JSON file} {--name= : Override dataset name (defaults to the file basename)} {--output= : Destination PHP file (defaults to database/evals/{name}-dataset.php)} {--force : Overwrite the destination file if it already exists}
```

> Import a CSV or JSON file into a Proofread dataset PHP file.

### Methods

#### `handle()`

```php
public function handle(): int
```

---

## `LintCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/LintCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/LintCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

### Artisan signature

```bash
php artisan proofread:lint {agents* : FQCNs of Agent classes to lint} {--format=table : Output format: table, json, or markdown} {--severity=all : Minimum severity to report: all, info, warning, error} {--with-judge : Also apply the SemanticQualityRule (LLM-based analysis)}
```

> Static analysis of Agent instructions: detect missing roles, ambiguity, contradictions, and more.

### Methods

#### `handle()`

```php
public function handle(PromptLinter $linter): int
```

---

## `ProofreadMakeAssertionCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands\Make`
- **Source:** [src/Console/Commands/Make/ProofreadMakeAssertionCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/Make/ProofreadMakeAssertionCommand.php)
- **Extends:** `Illuminate\Console\GeneratorCommand`
- **Implements:** `Illuminate\Contracts\Console\PromptsForMissingInput`, `Symfony\Component\Console\Command\SignalableCommandInterface`

---

## `ProofreadMakeDatasetCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands\Make`
- **Source:** [src/Console/Commands/Make/ProofreadMakeDatasetCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/Make/ProofreadMakeDatasetCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

### Artisan signature

```bash
php artisan proofread:make-dataset {name : The slug or class-like name of the dataset} {--path= : Destination directory (defaults to database/evals)} {--force : Overwrite the dataset file if it already exists}
```

> Create a new Proofread dataset PHP file

### Methods

#### `handle()`

```php
public function handle(Filesystem $files): int
```

---

## `ProofreadMakeSuiteCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands\Make`
- **Source:** [src/Console/Commands/Make/ProofreadMakeSuiteCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/Make/ProofreadMakeSuiteCommand.php)
- **Extends:** `Illuminate\Console\GeneratorCommand`
- **Implements:** `Illuminate\Contracts\Console\PromptsForMissingInput`, `Symfony\Component\Console\Command\SignalableCommandInterface`

---

## `RunEvalsCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/RunEvalsCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/RunEvalsCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

### Artisan signature

```bash
php artisan evals:run {suites* : FQCNs of EvalSuite subclasses to run} {--junit= : Write JUnit XML to this path (one file per suite when multiple are given)} {--fail-fast : Stop at the first suite that fails or errors} {--filter= : Case-insensitive substring filter against case meta.name or stringified input} {--persist : Persist each run to the database via EvalPersister} {--queue : Dispatch each suite to the queue instead of running inline} {--commit-sha= : Commit SHA attached to the persisted run (only used with --queue)} {--concurrency=1 : Run up to N cases in parallel. Default 1 (sequential). Only beneficial for I/O-bound subjects.} {--fake-judge= : Fake the judge agent for Rubric assertions: "pass", "fail", or a JSON file path} {--gate-pass-rate= : Fail the command (exit 1) if the overall pass rate is below this ratio (0.0 - 1.0)} {--gate-cost-max= : Fail the command (exit 1) if the total observed cost in USD exceeds this value}
```

> Run one or more Proofread eval suites and report the results.

### Methods

#### `handle()`

```php
public function handle(EvalRunner $runner, EvalPersister $persister): int
```

---

## `RunProviderComparisonCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/RunProviderComparisonCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/RunProviderComparisonCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

### Artisan signature

```bash
php artisan evals:providers {suite : FQCN of a MultiSubjectEvalSuite} {--persist : Persist the comparison and its runs to the database} {--commit-sha= : Commit SHA attached to the persisted comparison} {--concurrency=1 : Cases per run to execute in parallel (inner)} {--provider-concurrency=0 : Subjects to run in parallel (outer). 0 = all} {--fake-judge= : Fake the judge agent for Rubric assertions: "pass", "fail", or a JSON file path} {--format=table : Output format: table | json}
```

> Run a MultiSubjectEvalSuite against all declared subjects and report a comparison matrix.

### Methods

#### `handle()`

```php
public function handle(ComparisonRunner $runner, ComparisonPersister $persister): int
```

---

## `ShadowAlertCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/ShadowAlertCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/ShadowAlertCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Evaluate the rolling shadow pass rate for one or all agents and dispatch a
ShadowPassRateDroppedNotification for each agent whose pass rate has dropped
below the configured threshold. Alerts are deduped via the cache so an agent
below threshold does not page on every scheduled run.

Exit codes follow the convention that alerts are business signals, not
command failures: the command returns 0 whenever it ran cleanly (including
when it dispatched notifications), and a non-zero code only on internal
errors surfaced by the runtime.

### Artisan signature

```bash
php artisan shadow:alert {--agent= : Filter by agent FQCN} {--dry-run : Evaluate and print alerts without dispatching notifications or marking dedup}
```

> Check shadow pass rates against threshold and dispatch alerts for regressions.

### Methods

#### `handle()`

```php
public function handle(ShadowAlertService $service): int
```

---

## `ShadowEvaluateCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/ShadowEvaluateCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/ShadowEvaluateCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

### Artisan signature

```bash
php artisan shadow:evaluate {--agent= : Filter by agent FQCN} {--since= : Only evaluate captures captured since this duration (e.g. 1h, 24h, 7d)} {--batch=100 : Maximum captures to process in a single run} {--force : Re-evaluate captures that already have a ShadowEval} {--dry-run : Do not persist evaluations; report what would be done}
```

> Evaluate shadow captures against their registered assertions.

### Methods

#### `handle()`

```php
public function handle(ShadowEvaluator $evaluator): int
```

---

## `SimulateCostCommand`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Console\Commands`
- **Source:** [src/Console/Commands/SimulateCostCommand.php](https://github.com/mosaiqo/proofread/blob/main/src/Console/Commands/SimulateCostCommand.php)
- **Extends:** `Illuminate\Console\Command`
- **Implements:** `Symfony\Component\Console\Command\SignalableCommandInterface`

Simulate what an agent's historical production traffic would have cost
under alternative models. Analyzes shadow captures inside a time window
and produces a side-by-side cost comparison to help decide whether
switching models is worth it.

### Artisan signature

```bash
php artisan evals:cost-simulate {agent : FQCN of an Agent to simulate} {--days=30 : Number of days back from now to include} {--model=* : Limit the simulation to these alternative models (repeatable or comma-separated)} {--format=table : Output format: table or json}
```

> Project the historical cost of an agent under alternative models using shadow captures.

### Methods

#### `handle()`

```php
public function handle(CostSimulator $simulator): int
```

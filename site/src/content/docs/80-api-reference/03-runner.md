---
title: "Runner & suites"
section: "API Reference"
---

# Runner & suites

> **[info]** This page is auto-generated from PHP docblocks.
> Regenerate with `composer docs:api` after editing any docblock.

Eval runners, persisters, subject resolution, and suite base classes.

## Summary

| Class | Kind | Description |
|---|---|---|
| [`ComparisonPersister`](#comparisonpersister) | Final class | — |
| [`ComparisonRunner`](#comparisonrunner) | Final class | — |
| [`ConcurrencyDriver`](#concurrencydriver) | Interface | Minimal indirection over Laravel's Concurrency facade so that EvalRunner can be tested without forking processes and so we can swap drivers without touching call sites. |
| [`EvalPersister`](#evalpersister) | Class | Converts an in-memory EvalRun value object into persisted Eloquent rows. |
| [`EvalRunner`](#evalrunner) | Final class | — |
| [`EvalSuite`](#evalsuite) | Abstract class | — |
| [`LaravelConcurrencyDriver`](#laravelconcurrencydriver) | Final class | — |
| [`MultiSubjectEvalSuite`](#multisubjectevalsuite) | Abstract class | Abstract base for suites that evaluate the same dataset against multiple subjects (typically different models, providers, or prompt variations). |
| [`SubjectInvocation`](#subjectinvocation) | Final class | — |
| [`SubjectResolver`](#subjectresolver) | Final class | — |
| [`SyncConcurrencyDriver`](#syncconcurrencydriver) | Final class | Executes tasks sequentially in-process. Useful for tests and as a safe fallback when real concurrency drivers are unavailable. Preserves insertion order. |

---

## `ComparisonPersister`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Runner`
- **Source:** [src/Runner/ComparisonPersister.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/ComparisonPersister.php)

### Methods

#### `__construct()`

```php
public function __construct(EvalPersister $runPersister)
```

#### `persist()`

```php
public function persist(EvalComparison $comparison, ?string $suiteClass = null, ?string $commitSha = null): EvalComparison
```

---

## `ComparisonRunner`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Runner`
- **Source:** [src/Runner/ComparisonRunner.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/ComparisonRunner.php)

### Methods

#### `__construct()`

```php
public function __construct(EvalRunner $runner, ConcurrencyDriver $concurrency)
```

#### `run()`

```php
public function run(MultiSubjectEvalSuite $suite, int $providerConcurrency = 0, int $caseConcurrency = 1): EvalComparison
```

Run the suite against each declared subject and return the aggregate.

---

## `ConcurrencyDriver`

- **Kind:** Interface
- **Namespace:** `Mosaiqo\Proofread\Runner\Concurrency`
- **Source:** [src/Runner/Concurrency/ConcurrencyDriver.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/Concurrency/ConcurrencyDriver.php)

Minimal indirection over Laravel's Concurrency facade so that EvalRunner
can be tested without forking processes and so we can swap drivers
without touching call sites.

### Methods

#### `run()`

```php
public function run(array $tasks): array
```

Run the given closures in parallel and return an array of their
results, preserving the input key order.

---

## `EvalPersister`

- **Kind:** Class
- **Namespace:** `Mosaiqo\Proofread\Runner`
- **Source:** [src/Runner/EvalPersister.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/EvalPersister.php)

Converts an in-memory EvalRun value object into persisted Eloquent rows.

Consumes the immutable Support\EvalRun without mutating it. The entire
operation runs inside a single DB transaction so partial runs never leak
into the database.

### Methods

#### `persist()`

```php
public function persist(EvalRun $run, ?string $suiteClass = null, ?string $commitSha = null, ?string $subjectType = null, ?string $subjectClass = null, ?string $comparisonId = null, ?string $subjectLabel = null): EvalRun
```

---

## `EvalRunner`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Runner`
- **Source:** [src/Runner/EvalRunner.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/EvalRunner.php)

### Methods

#### `__construct()`

```php
public function __construct(?SubjectResolver $resolver = null, ?ConcurrencyDriver $concurrencyDriver = null)
```

#### `run()`

```php
public function run(mixed $subject, Dataset $dataset, array $assertions, int $concurrency = 1): EvalRun
```

Run the subject against every case in the dataset and evaluate assertions.

When `$subject` is a callable, it is invoked as `fn (mixed $input, array $case): mixed`
where `$input` is `$case['input']` pre-unwrapped and `$case` is the full case array.
See {@see \EvalSuite::subject()} for accepted subject shapes.

#### `runSuite()`

```php
public function runSuite(EvalSuite $suite, int $concurrency = 1, ?Closure $filter = null): EvalRun
```

Run an entire EvalSuite orchestrating its lifecycle.

Invokes $suite->setUp(), resolves the subject once, then iterates
the dataset asking the suite for per-case assertions via
{@see \EvalSuite::assertionsFor()}. tearDown runs in a finally
block so it triggers even if subject or assertions throw; it is
skipped when setUp itself throws, matching classic xUnit semantics.

---

## `EvalSuite`

- **Kind:** Abstract class
- **Namespace:** `Mosaiqo\Proofread\Suite`
- **Source:** [src/Suite/EvalSuite.php](https://github.com/mosaiqo/proofread/blob/main/src/Suite/EvalSuite.php)

### Methods

#### `dataset()`

```php
public abstract function dataset(): Dataset
```

#### `subject()`

```php
public abstract function subject(): mixed
```

Returns the subject under evaluation.

Can be one of:
- A {@see \Closure} / callable — invoked as `fn (mixed $input, array $case): mixed`
  where `$input` is `$case['input']` pre-unwrapped and `$case` is the full case
  array (including `expected`, `meta`, etc.).
- A class-string FQCN of a class implementing {@see \Agent}
  — resolved from the container and invoked with the case input as the prompt.
- An instance of an Agent — invoked as above.

For callables with multiple named inputs, have each case's `input` be an
associative array and unwrap inside the closure:

```php
public function subject(): mixed
{
    return fn (array $input): string =>
        $this->generator->generate($input['agent'], $input['task']);
}
```

Type validation is performed by the runner, not by the suite.

#### `assertions()`

```php
public abstract function assertions(): array
```

#### `assertionsFor()`

```php
public function assertionsFor(array $case): array
```

Returns the assertions to run against a specific case.

Override to read per-case metadata (e.g. $case['meta']['expected_count'])
and compose assertions that vary per case. The default delegates to the
shared assertions() list.

Invoked once per case at run time. The CLI header no longer invokes
this method — override detection is performed via reflection to
preserve single-pass execution. Implementations should still be
cheap: avoid container-heavy work or I/O here; construct lightweight
assertion objects and defer expensive wiring to the assertion's own
run() method.

#### `name()`

```php
public function name(): string
```

#### `setUp()`

```php
public function setUp(): void
```

Lifecycle hook invoked before dataset/subject/assertions are read.

Override to set up database state, tenant context, or other
prerequisites that the suite's data depends on.

#### `tearDown()`

```php
public function tearDown(): void
```

Lifecycle hook invoked after the suite finishes running.

Called in a finally block so it runs even if the subject throws.

---

## `LaravelConcurrencyDriver`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Runner\Concurrency`
- **Source:** [src/Runner/Concurrency/LaravelConcurrencyDriver.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/Concurrency/LaravelConcurrencyDriver.php)
- **Implements:** `Mosaiqo\Proofread\Runner\Concurrency\ConcurrencyDriver`

### Methods

#### `run()`

```php
public function run(array $tasks): array
```

---

## `MultiSubjectEvalSuite`

- **Kind:** Abstract class
- **Namespace:** `Mosaiqo\Proofread\Suite`
- **Source:** [src/Suite/MultiSubjectEvalSuite.php](https://github.com/mosaiqo/proofread/blob/main/src/Suite/MultiSubjectEvalSuite.php)
- **Extends:** `Mosaiqo\Proofread\Suite\EvalSuite`

Abstract base for suites that evaluate the same dataset against multiple
subjects (typically different models, providers, or prompt variations).

Implementers override subjects() returning a map of label -> subject.
Labels become column headers in comparison reports. Subjects follow the
same contract as EvalSuite::subject() -- callables, Agent FQCNs, or
Agent instances.

When run through a legacy single-subject runner, behaves as a regular
EvalSuite by returning the first subject from subjects().

### Methods

#### `subjects()`

```php
public abstract function subjects(): array
```

#### `subject()`

```php
public final function subject(): mixed
```

Returns the first subject from subjects() for backward compatibility
with runners that only understand single-subject suites.

---

## `SubjectInvocation`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Runner`
- **Source:** [src/Runner/SubjectInvocation.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/SubjectInvocation.php)

### Named constructors & static methods

#### `make()`

```php
public static function make(mixed $output, array $metadata = []): self
```

### Public properties

- readonly `mixed $output`
- readonly `array $metadata`

---

## `SubjectResolver`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Runner`
- **Source:** [src/Runner/SubjectResolver.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/SubjectResolver.php)

### Methods

#### `__construct()`

```php
public function __construct(?PricingTable $pricing = null)
```

#### `resolve()`

```php
public function resolve(mixed $subject): Closure
```

---

## `SyncConcurrencyDriver`

- **Kind:** Final class
- **Namespace:** `Mosaiqo\Proofread\Runner\Concurrency`
- **Source:** [src/Runner/Concurrency/SyncConcurrencyDriver.php](https://github.com/mosaiqo/proofread/blob/main/src/Runner/Concurrency/SyncConcurrencyDriver.php)
- **Implements:** `Mosaiqo\Proofread\Runner\Concurrency\ConcurrencyDriver`

Executes tasks sequentially in-process. Useful for tests and as a safe
fallback when real concurrency drivers are unavailable. Preserves
insertion order.

### Methods

#### `run()`

```php
public function run(array $tasks): array
```

### Public properties

- `int $invocations`
- `array $taskCountPerInvocation`

---
title: "Contracts & interfaces"
section: "API Reference"
---

# Contracts & interfaces

> **[info]** This page is auto-generated from PHP docblocks.
> Regenerate with `composer docs:api` after editing any docblock.

Interfaces and contracts implemented by Proofread internals and by user code that plugs into the eval runtime.

## Summary

| Class | Kind | Description |
|---|---|---|
| [`Assertion`](#assertion) | Interface | — |
| [`ConcurrencyDriver`](#concurrencydriver) | Interface | Minimal indirection over Laravel's Concurrency facade so that EvalRunner can be tested without forking processes and so we can swap drivers without touching call sites. |
| [`LaravelConcurrencyDriver`](#laravelconcurrencydriver) | Final class | — |
| [`LintRule`](#lintrule) | Interface | — |
| [`RandomNumberProvider`](#randomnumberprovider) | Interface | — |
| [`SyncConcurrencyDriver`](#syncconcurrencydriver) | Final class | Executes tasks sequentially in-process. Useful for tests and as a safe fallback when real concurrency drivers are unavailable. Preserves insertion order. |

---

## `Assertion`

- **Kind:** Interface
- **Namespace:** `Mosaiqo\Proofread\Contracts`
- **Source:** [src/Contracts/Assertion.php](https://github.com/mosaiqo/proofread/blob/main/src/Contracts/Assertion.php)

### Methods

#### `run()`

```php
public function run(mixed $output, array $context = []): AssertionResult
```

#### `name()`

```php
public function name(): string
```

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

## `RandomNumberProvider`

- **Kind:** Interface
- **Namespace:** `Mosaiqo\Proofread\Shadow\Contracts`
- **Source:** [src/Shadow/Contracts/RandomNumberProvider.php](https://github.com/mosaiqo/proofread/blob/main/src/Shadow/Contracts/RandomNumberProvider.php)

### Methods

#### `between01()`

```php
public function between01(): float
```

Returns a float in [0.0, 1.0).

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

---
title: "Eloquent models"
section: "API Reference"
---

# Eloquent models

> **[info]** This page is auto-generated from PHP docblocks.
> Regenerate with `composer docs:api` after editing any docblock.

Persisted eval runs, results, datasets, comparisons, and shadow captures. Only members declared on each class are shown — inherited Eloquent members are omitted.

## Summary

| Class | Kind | Description |
|---|---|---|
| [`EvalComparison`](#evalcomparison) | Class | — |
| [`EvalDataset`](#evaldataset) | Class | — |
| [`EvalDatasetVersion`](#evaldatasetversion) | Class | — |
| [`EvalResult`](#evalresult) | Class | Persisted Eloquent representation of a single case result within a run. |
| [`EvalRun`](#evalrun) | Class | Persisted Eloquent representation of an eval run. |
| [`ShadowCapture`](#shadowcapture) | Class | Persisted Eloquent representation of a shadow capture: a real production agent invocation (sampled + sanitized) stored for asynchronous evaluation. |
| [`ShadowEval`](#shadoweval) | Class | Persisted Eloquent representation of a single shadow evaluation: the result of running a set of assertions against a previously-captured production agent invocation (ShadowCapture). |

---

## `EvalComparison`

- **Kind:** Class
- **Namespace:** `Mosaiqo\Proofread\Models`
- **Source:** [src/Models/EvalComparison.php](https://github.com/mosaiqo/proofread/blob/main/src/Models/EvalComparison.php)
- **Extends:** `Illuminate\Database\Eloquent\Model`
- **Implements:** `Illuminate\Contracts\Routing\UrlRoutable`, `Stringable`, `Illuminate\Contracts\Queue\QueueableEntity`, `JsonSerializable`, `Illuminate\Contracts\Support\Jsonable`, `Illuminate\Contracts\Broadcasting\HasBroadcastChannel`, `Illuminate\Contracts\Support\CanBeEscapedWhenCastToString`, `ArrayAccess`, `Illuminate\Contracts\Support\Arrayable`

### Methods

#### `dataset()`

```php
public function dataset(): BelongsTo
```

#### `datasetVersion()`

```php
public function datasetVersion(): BelongsTo
```

#### `runs()`

```php
public function runs(): HasMany
```

#### `bestByPassRate()`

```php
public function bestByPassRate(): ?EvalRun
```

Returns the run with the highest pass rate. Falls back to the fastest
run as tiebreaker. Returns null when the comparison has no runs.

#### `cheapest()`

```php
public function cheapest(): ?EvalRun
```

Returns the run with the lowest total_cost_usd, ignoring runs where
the cost is null. Returns null if every run has a null cost.

#### `fastest()`

```php
public function fastest(): ?EvalRun
```

Returns the run with the lowest duration. Returns null when the
comparison has no runs.

#### `getKeyType()`

```php
public function getKeyType()
```

Get the auto-incrementing key type.

#### `getIncrementing()`

```php
public function getIncrementing()
```

Get the value indicating whether the IDs are incrementing.

#### `resolveRouteBindingQuery()`

```php
public function resolveRouteBindingQuery($query, $value, $field = null)
```

Retrieve the model for a bound value.

**Throws:** `\Illuminate\Database\Eloquent\ModelNotFoundException`

#### `newUniqueId()`

```php
public function newUniqueId()
```

Generate a new unique key for the model.

#### `uniqueIds()`

```php
public function uniqueIds()
```

Get the columns that should receive a unique identifier.

#### `initializeHasUniqueStringIds()`

```php
public function initializeHasUniqueStringIds()
```

Initialize the trait.

---

## `EvalDataset`

- **Kind:** Class
- **Namespace:** `Mosaiqo\Proofread\Models`
- **Source:** [src/Models/EvalDataset.php](https://github.com/mosaiqo/proofread/blob/main/src/Models/EvalDataset.php)
- **Extends:** `Illuminate\Database\Eloquent\Model`
- **Implements:** `Illuminate\Contracts\Routing\UrlRoutable`, `Stringable`, `Illuminate\Contracts\Queue\QueueableEntity`, `JsonSerializable`, `Illuminate\Contracts\Support\Jsonable`, `Illuminate\Contracts\Broadcasting\HasBroadcastChannel`, `Illuminate\Contracts\Support\CanBeEscapedWhenCastToString`, `ArrayAccess`, `Illuminate\Contracts\Support\Arrayable`

### Methods

#### `runs()`

```php
public function runs(): HasMany
```

#### `versions()`

```php
public function versions(): HasMany
```

#### `latestVersion()`

```php
public function latestVersion(): HasOne
```

#### `getKeyType()`

```php
public function getKeyType()
```

Get the auto-incrementing key type.

#### `getIncrementing()`

```php
public function getIncrementing()
```

Get the value indicating whether the IDs are incrementing.

#### `resolveRouteBindingQuery()`

```php
public function resolveRouteBindingQuery($query, $value, $field = null)
```

Retrieve the model for a bound value.

**Throws:** `\Illuminate\Database\Eloquent\ModelNotFoundException`

#### `newUniqueId()`

```php
public function newUniqueId()
```

Generate a new unique key for the model.

#### `uniqueIds()`

```php
public function uniqueIds()
```

Get the columns that should receive a unique identifier.

#### `initializeHasUniqueStringIds()`

```php
public function initializeHasUniqueStringIds()
```

Initialize the trait.

---

## `EvalDatasetVersion`

- **Kind:** Class
- **Namespace:** `Mosaiqo\Proofread\Models`
- **Source:** [src/Models/EvalDatasetVersion.php](https://github.com/mosaiqo/proofread/blob/main/src/Models/EvalDatasetVersion.php)
- **Extends:** `Illuminate\Database\Eloquent\Model`
- **Implements:** `Illuminate\Contracts\Routing\UrlRoutable`, `Stringable`, `Illuminate\Contracts\Queue\QueueableEntity`, `JsonSerializable`, `Illuminate\Contracts\Support\Jsonable`, `Illuminate\Contracts\Broadcasting\HasBroadcastChannel`, `Illuminate\Contracts\Support\CanBeEscapedWhenCastToString`, `ArrayAccess`, `Illuminate\Contracts\Support\Arrayable`

### Methods

#### `dataset()`

```php
public function dataset(): BelongsTo
```

#### `runs()`

```php
public function runs(): HasMany
```

#### `getKeyType()`

```php
public function getKeyType()
```

Get the auto-incrementing key type.

#### `getIncrementing()`

```php
public function getIncrementing()
```

Get the value indicating whether the IDs are incrementing.

#### `resolveRouteBindingQuery()`

```php
public function resolveRouteBindingQuery($query, $value, $field = null)
```

Retrieve the model for a bound value.

**Throws:** `\Illuminate\Database\Eloquent\ModelNotFoundException`

#### `newUniqueId()`

```php
public function newUniqueId()
```

Generate a new unique key for the model.

#### `uniqueIds()`

```php
public function uniqueIds()
```

Get the columns that should receive a unique identifier.

#### `initializeHasUniqueStringIds()`

```php
public function initializeHasUniqueStringIds()
```

Initialize the trait.

---

## `EvalResult`

- **Kind:** Class
- **Namespace:** `Mosaiqo\Proofread\Models`
- **Source:** [src/Models/EvalResult.php](https://github.com/mosaiqo/proofread/blob/main/src/Models/EvalResult.php)
- **Extends:** `Illuminate\Database\Eloquent\Model`
- **Implements:** `Illuminate\Contracts\Routing\UrlRoutable`, `Stringable`, `Illuminate\Contracts\Queue\QueueableEntity`, `JsonSerializable`, `Illuminate\Contracts\Support\Jsonable`, `Illuminate\Contracts\Broadcasting\HasBroadcastChannel`, `Illuminate\Contracts\Support\CanBeEscapedWhenCastToString`, `ArrayAccess`, `Illuminate\Contracts\Support\Arrayable`

Persisted Eloquent representation of a single case result within a run.

Not to be confused with the in-memory value object
Mosaiqo\Proofread\Support\EvalResult.

### Methods

#### `run()`

```php
public function run(): BelongsTo
```

#### `getKeyType()`

```php
public function getKeyType()
```

Get the auto-incrementing key type.

#### `getIncrementing()`

```php
public function getIncrementing()
```

Get the value indicating whether the IDs are incrementing.

#### `resolveRouteBindingQuery()`

```php
public function resolveRouteBindingQuery($query, $value, $field = null)
```

Retrieve the model for a bound value.

**Throws:** `\Illuminate\Database\Eloquent\ModelNotFoundException`

#### `newUniqueId()`

```php
public function newUniqueId()
```

Generate a new unique key for the model.

#### `uniqueIds()`

```php
public function uniqueIds()
```

Get the columns that should receive a unique identifier.

#### `initializeHasUniqueStringIds()`

```php
public function initializeHasUniqueStringIds()
```

Initialize the trait.

### Public properties

- `$timestamps`

---

## `EvalRun`

- **Kind:** Class
- **Namespace:** `Mosaiqo\Proofread\Models`
- **Source:** [src/Models/EvalRun.php](https://github.com/mosaiqo/proofread/blob/main/src/Models/EvalRun.php)
- **Extends:** `Illuminate\Database\Eloquent\Model`
- **Implements:** `Illuminate\Contracts\Routing\UrlRoutable`, `Stringable`, `Illuminate\Contracts\Queue\QueueableEntity`, `JsonSerializable`, `Illuminate\Contracts\Support\Jsonable`, `Illuminate\Contracts\Broadcasting\HasBroadcastChannel`, `Illuminate\Contracts\Support\CanBeEscapedWhenCastToString`, `ArrayAccess`, `Illuminate\Contracts\Support\Arrayable`

Persisted Eloquent representation of an eval run.

Not to be confused with the in-memory value object
Mosaiqo\Proofread\Support\EvalRun, which is what the runner returns.

### Methods

#### `dataset()`

```php
public function dataset(): BelongsTo
```

#### `datasetVersion()`

```php
public function datasetVersion(): BelongsTo
```

#### `comparison()`

```php
public function comparison(): BelongsTo
```

#### `results()`

```php
public function results(): HasMany
```

#### `failures()`

```php
public function failures(): Builder
```

#### `passRate()`

```php
public function passRate(): float
```

#### `getKeyType()`

```php
public function getKeyType()
```

Get the auto-incrementing key type.

#### `getIncrementing()`

```php
public function getIncrementing()
```

Get the value indicating whether the IDs are incrementing.

#### `resolveRouteBindingQuery()`

```php
public function resolveRouteBindingQuery($query, $value, $field = null)
```

Retrieve the model for a bound value.

**Throws:** `\Illuminate\Database\Eloquent\ModelNotFoundException`

#### `newUniqueId()`

```php
public function newUniqueId()
```

Generate a new unique key for the model.

#### `uniqueIds()`

```php
public function uniqueIds()
```

Get the columns that should receive a unique identifier.

#### `initializeHasUniqueStringIds()`

```php
public function initializeHasUniqueStringIds()
```

Initialize the trait.

---

## `ShadowCapture`

- **Kind:** Class
- **Namespace:** `Mosaiqo\Proofread\Models`
- **Source:** [src/Models/ShadowCapture.php](https://github.com/mosaiqo/proofread/blob/main/src/Models/ShadowCapture.php)
- **Extends:** `Illuminate\Database\Eloquent\Model`
- **Implements:** `Illuminate\Contracts\Routing\UrlRoutable`, `Stringable`, `Illuminate\Contracts\Queue\QueueableEntity`, `JsonSerializable`, `Illuminate\Contracts\Support\Jsonable`, `Illuminate\Contracts\Broadcasting\HasBroadcastChannel`, `Illuminate\Contracts\Support\CanBeEscapedWhenCastToString`, `ArrayAccess`, `Illuminate\Contracts\Support\Arrayable`

Persisted Eloquent representation of a shadow capture: a real production
agent invocation (sampled + sanitized) stored for asynchronous evaluation.

### Methods

#### `evals()`

```php
public function evals(): HasMany
```

#### `scopeForAgent()`

```php
public function scopeForAgent(Builder $query, string $agentClass): Builder
```

#### `scopeCapturedBetween()`

```php
public function scopeCapturedBetween(Builder $query, DateTimeInterface $from, DateTimeInterface $to): Builder
```

#### `getKeyType()`

```php
public function getKeyType()
```

Get the auto-incrementing key type.

#### `getIncrementing()`

```php
public function getIncrementing()
```

Get the value indicating whether the IDs are incrementing.

#### `resolveRouteBindingQuery()`

```php
public function resolveRouteBindingQuery($query, $value, $field = null)
```

Retrieve the model for a bound value.

**Throws:** `\Illuminate\Database\Eloquent\ModelNotFoundException`

#### `newUniqueId()`

```php
public function newUniqueId()
```

Generate a new unique key for the model.

#### `uniqueIds()`

```php
public function uniqueIds()
```

Get the columns that should receive a unique identifier.

#### `initializeHasUniqueStringIds()`

```php
public function initializeHasUniqueStringIds()
```

Initialize the trait.

---

## `ShadowEval`

- **Kind:** Class
- **Namespace:** `Mosaiqo\Proofread\Models`
- **Source:** [src/Models/ShadowEval.php](https://github.com/mosaiqo/proofread/blob/main/src/Models/ShadowEval.php)
- **Extends:** `Illuminate\Database\Eloquent\Model`
- **Implements:** `Illuminate\Contracts\Routing\UrlRoutable`, `Stringable`, `Illuminate\Contracts\Queue\QueueableEntity`, `JsonSerializable`, `Illuminate\Contracts\Support\Jsonable`, `Illuminate\Contracts\Broadcasting\HasBroadcastChannel`, `Illuminate\Contracts\Support\CanBeEscapedWhenCastToString`, `ArrayAccess`, `Illuminate\Contracts\Support\Arrayable`

Persisted Eloquent representation of a single shadow evaluation: the result
of running a set of assertions against a previously-captured production
agent invocation (ShadowCapture).

### Methods

#### `capture()`

```php
public function capture(): BelongsTo
```

#### `scopeForAgent()`

```php
public function scopeForAgent(Builder $query, string $agentClass): Builder
```

#### `scopePassedOnly()`

```php
public function scopePassedOnly(Builder $query): Builder
```

#### `scopeFailedOnly()`

```php
public function scopeFailedOnly(Builder $query): Builder
```

#### `scopeEvaluatedBetween()`

```php
public function scopeEvaluatedBetween(Builder $query, DateTimeInterface $from, DateTimeInterface $to): Builder
```

#### `getKeyType()`

```php
public function getKeyType()
```

Get the auto-incrementing key type.

#### `getIncrementing()`

```php
public function getIncrementing()
```

Get the value indicating whether the IDs are incrementing.

#### `resolveRouteBindingQuery()`

```php
public function resolveRouteBindingQuery($query, $value, $field = null)
```

Retrieve the model for a bound value.

**Throws:** `\Illuminate\Database\Eloquent\ModelNotFoundException`

#### `newUniqueId()`

```php
public function newUniqueId()
```

Generate a new unique key for the model.

#### `uniqueIds()`

```php
public function uniqueIds()
```

Get the columns that should receive a unique identifier.

#### `initializeHasUniqueStringIds()`

```php
public function initializeHasUniqueStringIds()
```

Initialize the trait.

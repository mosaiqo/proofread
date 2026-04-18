---
title: Eval suites
section: Concepts
---

# Eval suites

An `EvalSuite` is a class that bundles a dataset, a subject, and a list of
assertions. It is the unit of reuse: one suite per thing you want to
evaluate over time.

## The contract

`Mosaiqo\Proofread\Suite\EvalSuite` is abstract and exposes three required
methods:

```php
abstract public function dataset(): Dataset;
abstract public function subject(): mixed;
abstract public function assertions(): array;
```

- `dataset()` returns a `Mosaiqo\Proofread\Support\Dataset`.
- `subject()` returns a callable, an Agent FQCN (class-string), or an Agent
  instance. The `SubjectResolver` decides how to invoke it.
- `assertions()` returns a `list<Mosaiqo\Proofread\Contracts\Assertion>` applied
  to every case.

### Suite name

Override `name(): string` if you want a stable label for logs, persistence, and
CLI output. Defaults to the class FQCN.

## Lifecycle hooks

- `setUp(): void` — runs before the dataset, subject, and assertions are read.
  Use it to seed state, set tenant context, or `Agent::fake()` in tests.
- `tearDown(): void` — runs in a `finally` block, even if the subject throws.
- `assertionsFor(array $case): array` — per-case assertion composition. Read
  `$case['meta']` to branch on case metadata and vary assertions by case.
  Defaults to `assertions()`.

## Scaffolding

Generate a suite file with the make command:

```bash
php artisan proofread:make-suite SentimentSuite
```

For a multi-subject (provider comparison) suite:

```bash
php artisan proofread:make-suite SentimentMatrixSuite --multi
```

## Example: single-subject suite

```php
namespace App\Evals;

use App\Agents\SentimentAgent;
use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class SentimentSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('sentiment', [
            ['input' => 'I love this product!',  'expected' => 'positive'],
            ['input' => 'This is terrible.',     'expected' => 'negative'],
            ['input' => 'It works as described.', 'expected' => 'neutral'],
        ]);
    }

    public function subject(): mixed
    {
        return SentimentAgent::class;
    }

    public function assertions(): array
    {
        return [
            RegexAssertion::make('/^(positive|negative|neutral)$/'),
            CostLimit::under(0.01),
        ];
    }
}
```

## MultiSubjectEvalSuite

Extends `EvalSuite` to evaluate the same dataset against multiple subjects
(typically different models or prompt variants). The labels become column
headers in comparison reports.

```php
namespace App\Evals;

use App\Agents\SentimentHaiku;
use App\Agents\SentimentOpus;
use App\Agents\SentimentSonnet;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class SentimentMatrixSuite extends MultiSubjectEvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('sentiment', [
            ['input' => 'I love this product!',  'expected' => 'positive'],
            ['input' => 'This is terrible.',     'expected' => 'negative'],
        ]);
    }

    public function subjects(): array
    {
        return [
            'haiku'  => SentimentHaiku::class,
            'sonnet' => SentimentSonnet::class,
            'opus'   => SentimentOpus::class,
        ];
    }

    public function assertions(): array
    {
        return [
            RegexAssertion::make('/^(positive|negative|neutral)$/'),
        ];
    }
}
```

Run it with:

```bash
php artisan evals:providers "App\\Evals\\SentimentMatrixSuite"
```

> **[info]** Suite `name()` is used for persistence, logs, and CLI output.
> Keep it stable — renaming a suite breaks historical comparisons.

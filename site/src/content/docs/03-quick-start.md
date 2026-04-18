---
title: Quick start
section: Start here
---

# Quick start

In 5 minutes, you will write your first eval suite against a plain PHP
callable — no API keys, no agent classes, no external services. This gets the
mechanics into your fingers before you wire up a real `laravel/ai` agent.

## 1. Define a callable subject

A "subject" is whatever gets evaluated. The simplest shape is a closure:

```php
$classifier = fn (string $input): string =>
    str_contains(strtolower($input), 'great') ? 'positive' : 'neutral';
```

## 2. Create a dataset

Datasets are immutable collections of cases. Each case has an `input` and
(optionally) an `expected` value plus arbitrary `meta`.

```php
use Mosaiqo\Proofread\Support\Dataset;

$dataset = Dataset::make('sentiment', [
    ['input' => 'This product is great!', 'expected' => 'positive'],
    ['input' => 'It works as expected.',  'expected' => 'neutral'],
]);
```

## 3. Pick assertions

Assertions verify the subject's output. You compose as many as you need per
case.

```php
use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Assertions\LengthAssertion;

$assertions = [
    ContainsAssertion::make('positive'),
    LengthAssertion::between(1, 30),
];
```

## 4. Run the eval

You have two entry points. Pick whichever fits your workflow.

### From Pest

```php
use Mosaiqo\Proofread\Proofread;

beforeAll(fn () => Proofread::registerPestExpectations());

it('classifies sentiment', function () use ($dataset, $assertions, $classifier) {
    expect($classifier)->toPassEval($dataset, $assertions);
});
```

### From Artisan (using an `EvalSuite` class)

```bash
php artisan evals:run "App\\Evals\\SentimentSuite"
```

See [Running evals](/docs/running-evals) for the full CLI, including
`--persist`, `--junit`, `--gate-pass-rate`, and concurrency.

## 5. Next steps

- [Core concepts](/docs/core-concepts) — the 5 building blocks.
- [Eval suites](/docs/eval-suites) — turn the snippets above into a reusable
  suite class.
- [Assertions](/docs/assertions) — the 15+ built-in assertions you can mix
  and match.

> **[success]** You have just run a deterministic eval. The same shape scales
> up to LLM-as-judge rubrics, embedding similarity, token budgets, and
> golden snapshots without changing the mental model.

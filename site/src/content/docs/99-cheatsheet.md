---
title: Cheatsheet
section: Reference
---

# Cheatsheet

Single-page copy-paste reference. 80% of what you will reach for, day-to-day.

## Install

```bash
composer require mosaiqo/proofread
php artisan vendor:publish --tag=proofread-migrations
php artisan migrate
```

## A complete eval suite

```php
namespace App\Evals;

use App\Agents\SentimentAgent;
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class SentimentSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        return Dataset::make('sentiment', [
            ['input' => 'I love this!',  'expected' => 'positive'],
            ['input' => 'Hate it.',      'expected' => 'negative'],
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
            CostLimit::under(0.005),
        ];
    }
}
```

## All assertions

```php
use Mosaiqo\Proofread\Assertions\ContainsAssertion;
use Mosaiqo\Proofread\Assertions\CostLimit;
use Mosaiqo\Proofread\Assertions\CountAssertion;
use Mosaiqo\Proofread\Assertions\GoldenSnapshot;
use Mosaiqo\Proofread\Assertions\HallucinationAssertion;
use Mosaiqo\Proofread\Assertions\JsonSchemaAssertion;
use Mosaiqo\Proofread\Assertions\LanguageAssertion;
use Mosaiqo\Proofread\Assertions\LatencyLimit;
use Mosaiqo\Proofread\Assertions\LengthAssertion;
use Mosaiqo\Proofread\Assertions\PiiLeakageAssertion;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Assertions\Similar;
use Mosaiqo\Proofread\Assertions\StructuredOutputAssertion;
use Mosaiqo\Proofread\Assertions\TokenBudget;
use Mosaiqo\Proofread\Assertions\Trajectory;

ContainsAssertion::make('needle');
RegexAssertion::make('/^yes|no$/i');
LengthAssertion::between(1, 500);
CountAssertion::equals(3);
JsonSchemaAssertion::fromAgent(MyStructuredAgent::class);

TokenBudget::maxTotal(2_000);
CostLimit::under(0.01);
LatencyLimit::under(1_500);

Rubric::make('is polite and concise');
Similar::to('expected text')->minScore(0.8);
HallucinationAssertion::against($groundTruth)->minScore(0.9);
LanguageAssertion::matches('en');

Trajectory::callsToolsInOrder(['search', 'summarize']);

GoldenSnapshot::fromContext();

StructuredOutputAssertion::conformsTo(MyAgent::class);

PiiLeakageAssertion::make();
```

## All Pest expectations

```php
use Mosaiqo\Proofread\Proofread;

beforeAll(fn () => Proofread::registerPestExpectations());

expect($subject)->toPassEval($dataset, $assertions);
expect(new SentimentSuite)->toPassSuite();
expect($output)->toPassAssertion(ContainsAssertion::make('hello'));
expect($output)->toPassRubric('is polite');
expect($run)->toCostUnder(0.05);
expect($output)->toMatchSchema(storage_path('schemas/invoice.json'));
expect($output)->toMatchGoldenSnapshot();
```

## All Artisan commands

| Command                                     | Notable flags                                                            |
| ------------------------------------------- | ------------------------------------------------------------------------ |
| `evals:run {suites*}`                       | `--persist`, `--junit`, `--fail-fast`, `--filter`, `--queue`, `--concurrency`, `--fake-judge`, `--gate-pass-rate`, `--gate-cost-max`, `--commit-sha` |
| `evals:compare {base} {head}`               | `latest` keyword supported.                                              |
| `evals:dataset:diff {name}`                 | Diff consecutive dataset versions.                                       |
| `evals:providers {suite}`                   | Run a `MultiSubjectEvalSuite`.                                           |
| `evals:benchmark`                           | Performance benchmarking.                                                |
| `evals:cluster`                             | Failure clustering.                                                      |
| `evals:coverage`                            | Coverage report.                                                         |
| `evals:export`                              | Export a run.                                                            |
| `evals:cost-simulate`                       | Dry-run cost projection.                                                 |
| `dataset:generate`                          | LLM-assisted dataset generation.                                         |
| `dataset:import`                            | Import from JSON / CSV / Promptfoo.                                      |
| `dataset:export`                            | Export to JSON / CSV.                                                    |
| `shadow:evaluate`                           | Evaluate captured shadow traffic.                                        |
| `shadow:alert`                              | Alert on shadow pass-rate drops.                                         |
| `proofread:lint {agents*}`                  | Static checks on Agent classes.                                          |
| `proofread:make-suite`                      | Scaffold an `EvalSuite`. `--multi` for `MultiSubjectEvalSuite`.          |
| `proofread:make-dataset`                    | Scaffold a `Dataset` provider.                                           |
| `proofread:make-assertion`                  | Scaffold a custom `Assertion`.                                           |

## CI workflow

```yaml
name: Evals
on: [pull_request]
jobs:
  evals:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-interaction --no-progress
      - run: |
          php artisan evals:run "App\\Evals\\SentimentSuite" \
            --persist \
            --junit=reports/evals.xml \
            --gate-pass-rate=0.9 \
            --gate-cost-max=1.00 \
            --commit-sha=${{ github.sha }}
```

## Shadow capture

Register a resolver and attach the middleware:

```php
use App\Agents\SupportAgent;
use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Proofread;

Proofread::registerShadowAssertions(SupportAgent::class, fn () => [
    Rubric::make('is polite and on-topic'),
]);
```

Attach the capture middleware to the route that invokes the agent, then
evaluate asynchronously:

```bash
php artisan shadow:evaluate
php artisan shadow:alert
```

## Multi-provider comparison

```php
namespace App\Evals;

use Mosaiqo\Proofread\Suite\MultiSubjectEvalSuite;

final class HaikuVsSonnetSuite extends MultiSubjectEvalSuite
{
    public function dataset(): \Mosaiqo\Proofread\Support\Dataset { /* ... */ }
    public function assertions(): array { /* ... */ }

    public function subjects(): array
    {
        return [
            'haiku'  => \App\Agents\HaikuAgent::class,
            'sonnet' => \App\Agents\SonnetAgent::class,
        ];
    }
}
```

```bash
php artisan evals:providers "App\\Evals\\HaikuVsSonnetSuite"
```

## CLI subject (Claude Code)

For subscription-based providers that only ship a CLI, not an API:

```php
use Mosaiqo\Proofread\Cli\Subjects\ClaudeCodeCliSubject;

public function subject(): mixed
{
    return ClaudeCodeCliSubject::make();
}
```

## Fake the judge in tests

```php
use Mosaiqo\Proofread\Judge\JudgeAgent;

beforeEach(function () {
    JudgeAgent::fake(fn (): string => json_encode([
        'pass'   => true,
        'score'  => 1.0,
        'reason' => 'stubbed',
    ]));
});
```

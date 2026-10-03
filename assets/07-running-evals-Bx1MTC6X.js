const n=`---
title: Running evals
section: Running
---

# Running evals

Three entry points. Pick whichever matches where the eval lives: inside a Pest
test, in CI via Artisan, or programmatically.

## Pest expectations

Register the expectations once per test suite (usually in \`Pest.php\` or a
\`beforeAll\`):

\`\`\`php
use Mosaiqo\\Proofread\\Proofread;

beforeAll(fn () => Proofread::registerPestExpectations());
\`\`\`

You then get:

### \`toPassEval($dataset, $assertions)\`

Run a dataset against the current subject with a list of assertions.

\`\`\`php
expect(SentimentAgent::class)->toPassEval($dataset, [
    RegexAssertion::make('/^(positive|negative|neutral)$/'),
]);
\`\`\`

### \`toPassSuite()\`

Run a full \`EvalSuite\`. After success, the expectation's \`value\` becomes the
\`EvalRun\`, so you can chain further expectations on the result.

\`\`\`php
use App\\Evals\\SentimentSuite;

$run = expect(new SentimentSuite)->toPassSuite()->value;

expect($run->pass_rate)->toBeGreaterThan(0.9);
\`\`\`

### \`toPassAssertion($assertion)\`

Run a single assertion against the expectation's subject.

\`\`\`php
expect('hello world')->toPassAssertion(ContainsAssertion::make('hello'));
\`\`\`

### \`toPassRubric($criteria, $options = [])\`

Shorthand for a \`Rubric\` assertion.

\`\`\`php
expect($output)->toPassRubric('is polite and on-topic');
\`\`\`

### \`toCostUnder($maxUsd)\`

Asserts the subject (an \`EvalRun\`) cost stayed below \`$maxUsd\`.

\`\`\`php
expect($run)->toCostUnder(0.05);
\`\`\`

### \`toMatchSchema($schema)\`

Validates a JSON output (string or array) against a JSON Schema (array or
path).

\`\`\`php
expect($output)->toMatchSchema(storage_path('schemas/invoice.json'));
\`\`\`

### \`toMatchGoldenSnapshot($key = null)\`

Compares output against a stored snapshot. Auto-creates the snapshot on first
run.

\`\`\`php
expect($output)->toMatchGoldenSnapshot();
\`\`\`

## Artisan CLI

The primary CI entry point:

\`\`\`bash
php artisan evals:run "App\\\\Evals\\\\SentimentSuite"
\`\`\`

### All flags

| Flag                  | Purpose                                                                 |
| --------------------- | ----------------------------------------------------------------------- |
| \`--junit=PATH\`        | Write JUnit XML (one file per suite when multiple suites are passed).   |
| \`--fail-fast\`         | Stop at the first suite that fails or errors.                           |
| \`--filter=TEXT\`       | Case-insensitive substring filter on \`meta.name\` or stringified input.  |
| \`--persist\`           | Persist each run to the database via \`EvalPersister\`.                   |
| \`--queue\`             | Dispatch each suite to the queue instead of running inline.             |
| \`--commit-sha=SHA\`    | Commit SHA attached to the persisted run (use with \`--queue\`).          |
| \`--concurrency=N\`     | Run up to N cases in parallel. Default 1 (sequential).                  |
| \`--fake-judge=SPEC\`   | Fake the judge agent: \`pass\`, \`fail\`, or a JSON file path.              |
| \`--gate-pass-rate=R\`  | Exit 1 if overall pass rate is below \`R\` (0.0 - 1.0).                   |
| \`--gate-cost-max=USD\` | Exit 1 if total observed cost exceeds this USD value.                   |

### Common recipes

CI gate on a minimum pass rate:

\`\`\`bash
php artisan evals:run "App\\\\Evals\\\\SentimentSuite" \\
  --persist \\
  --junit=reports/evals.xml \\
  --gate-pass-rate=0.95 \\
  --gate-cost-max=1.00
\`\`\`

Fast feedback while iterating:

\`\`\`bash
php artisan evals:run "App\\\\Evals\\\\SentimentSuite" \\
  --filter=edge \\
  --fail-fast \\
  --fake-judge=pass
\`\`\`

Parallel run for I/O-bound subjects:

\`\`\`bash
php artisan evals:run "App\\\\Evals\\\\SentimentSuite" --concurrency=4
\`\`\`

## Direct runner

For programmatic use (background jobs, custom commands, internal tooling):

\`\`\`php
use Mosaiqo\\Proofread\\Runner\\EvalRunner;

$run = (new EvalRunner)->runSuite(new SentimentSuite);
\`\`\`

Or, for lower-level control when you already have the pieces:

\`\`\`php
$run = (new EvalRunner)->run($subject, $dataset, $assertions);
\`\`\`

Reach for \`run()\` when you need to compose dataset/subject/assertions at
runtime. Use \`runSuite()\` for anything that should be reusable — tests,
scheduled jobs, CI.

## CI workflow

Publish a ready-made workflow:

\`\`\`bash
php artisan vendor:publish --tag=proofread-workflows
\`\`\`

Resulting \`.github/workflows/evals.yml\` (excerpt):

\`\`\`yaml
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
          php artisan evals:run "App\\\\Evals\\\\SentimentSuite" \\
            --junit=reports/evals.xml \\
            --gate-pass-rate=0.9
\`\`\`

> **[warn]** \`--concurrency > 1\` is unsafe for subjects that write to SQLite.
> Use it for LLM/HTTP-bound subjects only.
`;export{n as default};

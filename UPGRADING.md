# Upgrading

Pre-v1 releases may carry breaking changes in minor versions. This
document lists every upgrade that requires consumer action.

Versions without an entry upgrade cleanly with `composer update`.

## Upgrading to 0.6.0 from 0.5.x

No breaking changes. All additions. Optional adoption:

- To use the new scaffolding commands, no configuration is needed —
  they appear in `php artisan list` after upgrading.
- To override the generated stubs, publish them with
  `php artisan vendor:publish --tag=proofread-stubs`.
- To add the CI workflow, publish it with
  `php artisan vendor:publish --tag=proofread-workflows` and
  customize the suite FQCN in the resulting file.

## Upgrading to 0.5.1 from 0.5.0

### `evals:run` now honors `assertionsFor` overrides

If your `EvalSuite` subclass overrides `assertionsFor()` to add or
vary assertions per case, the CLI command `evals:run` invocation
now actually runs those assertions. Previously (0.2.0–0.5.0) the CLI
silently skipped them, reporting pass on cases that should have
failed.

If your CI shows new failures on `evals:run` after upgrading, those
are real regressions that the per-case assertions catch. Inspect the
`FAIL` output for the assertion name and reason.

No migration or code change is required on your side — the fix is
transparent to consumers with correct tests.

## Upgrading to 0.5.0 from 0.4.x

### `AssertionResult` is sealed

Only `AssertionResult` and `JudgeResult` may now inherit from
`AssertionResult`. If your code extends `AssertionResult` outside
the package, construction will now throw `LogicException`. Open an
issue if you have a legitimate use case and we can discuss extending
the allowed list.

### `toPassEval` / `toPassSuite` now set `$this->value`

Callers that relied on `->value` returning the original subject
after these expectations will see the resulting `EvalRun` instead.
Chain the expectation first, then inspect the run:

```php
// Old: $subject was the expect() value.
// New: the run replaces the value after the assertion passes.
$run = expect($agent)->toPassEval($dataset, $assertions)->value;
```

### `evals:run` header format changed for suites overriding `assertionsFor`

Suites that override `EvalSuite::assertionsFor()` previously saw a
range like `2 cases, 2-4 assertions per case` in the CLI header.
0.5.0 replaces that with a base count plus a "per-case may vary"
note: `2 cases, 2 base assertions (per-case may vary)`. Suites that
do not override `assertionsFor` retain the singular form
`N cases, M assertions per case`. If you scrape CLI output for
assertion counts, adapt your matchers accordingly.

## Upgrading to 0.4.0 from 0.3.x

No consumer action required. Multi-provider comparison is an opt-in
subsystem — existing single-subject suites behave unchanged.

## Upgrading to 0.3.0 from 0.2.x

### Migration filenames renamed

The three legacy migrations shipped by the package were renamed to
add a `2026_04_01_*` date prefix so they sort consistently alongside
newer migrations:

- `create_eval_datasets_table.php` -> `2026_04_01_000001_create_eval_datasets_table.php`
- `create_eval_runs_table.php` -> `2026_04_01_000002_create_eval_runs_table.php`
- `create_eval_results_table.php` -> `2026_04_01_000003_create_eval_results_table.php`

**If you already published the migrations via
`php artisan vendor:publish --tag=proofread-migrations` before
upgrading, no action is required** — your published copies carry
their own timestamps independent of the package.

**If you relied on `discoversMigrations()` auto-loading** (no
publish step), Laravel's migration registry saw the unprefixed
filenames as migration IDs. After upgrade the IDs change and Laravel
will try to re-run the renamed files, which fails on duplicate
tables. Manual fix:

```sql
UPDATE migrations
SET migration = '2026_04_01_000001_create_eval_datasets_table'
WHERE migration = 'create_eval_datasets_table';

UPDATE migrations
SET migration = '2026_04_01_000002_create_eval_runs_table'
WHERE migration = 'create_eval_runs_table';

UPDATE migrations
SET migration = '2026_04_01_000003_create_eval_results_table'
WHERE migration = 'create_eval_results_table';
```

Or simply run the update inside a `php artisan tinker`:

```php
use Illuminate\Support\Facades\DB;

foreach ([
    'create_eval_datasets_table'  => '2026_04_01_000001_create_eval_datasets_table',
    'create_eval_runs_table'      => '2026_04_01_000002_create_eval_runs_table',
    'create_eval_results_table'   => '2026_04_01_000003_create_eval_results_table',
] as $from => $to) {
    DB::table('migrations')->where('migration', $from)->update(['migration' => $to]);
}
```

### `EvalDataset.checksum` now lags the current run

Before 0.3.0, `eval_datasets.checksum` held the checksum of whatever
cases were being run. With 0.3.0's dataset versioning, the column
instead tracks the **most recently seen** checksum and may differ
from the checksum of any given historical run.

Queries that used `eval_datasets.checksum` as an exact match against
a run's dataset should migrate to join via
`eval_runs.dataset_version_id` -> `eval_dataset_versions.checksum`
instead:

```php
$run = EvalRun::with('datasetVersion')->find($ulid);
$exactChecksum = $run->datasetVersion?->checksum;
```

## Upgrading to 0.2.0 from 0.1.x

No consumer action required.

## Upgrading to 0.1.1 from 0.1.0

If you installed 0.1.0 and ran migrations, the shadow capture and
shadow eval tables would not have been created due to a
registration bug. Republish migrations after upgrading:

```bash
php artisan vendor:publish --tag=proofread-migrations --force
php artisan migrate
```

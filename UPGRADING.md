# Upgrading

Pre-v1 releases may carry breaking changes in minor versions. This
document lists every upgrade that requires consumer action.

Versions without an entry upgrade cleanly with `composer update`.

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

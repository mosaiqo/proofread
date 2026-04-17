<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Models\EvalComparison as EvalComparisonModel;
use Mosaiqo\Proofread\Models\EvalDatasetVersion as EvalDatasetVersionModel;
use Mosaiqo\Proofread\Models\EvalRun as EvalRunModel;
use Mosaiqo\Proofread\Runner\ComparisonPersister;
use Mosaiqo\Proofread\Runner\EvalPersister;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalComparison as SupportEvalComparison;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

function cmpMakeRun(Dataset $dataset, bool $passed = true, ?float $cost = 0.02): EvalRun
{
    $metadata = ['assertion_name' => 'stub'];
    if ($cost !== null) {
        $metadata['cost_usd'] = $cost;
    }

    $assertion = $passed
        ? AssertionResult::pass('ok', null, $metadata)
        : AssertionResult::fail('nope', null, $metadata);

    $result = EvalResult::make(
        ['input' => 'x'],
        'y',
        [$assertion],
        5.0,
    );

    return EvalRun::make($dataset, [$result], 10.0);
}

function cmpBuildComparison(bool $allPass = true, ?float $cost = 0.02): SupportEvalComparison
{
    $dataset = Dataset::make('cmp-persist', [['input' => 'x']]);

    return SupportEvalComparison::make('comparison-name', $dataset, [
        'haiku' => cmpMakeRun($dataset, $allPass, $cost),
        'sonnet' => cmpMakeRun($dataset, true, $cost),
    ], 123.0);
}

it('persists a comparison with aggregate stats', function (): void {
    $persister = new ComparisonPersister(new EvalPersister);

    $model = $persister->persist(cmpBuildComparison());

    expect(EvalComparisonModel::query()->count())->toBe(1)
        ->and($model->name)->toBe('comparison-name')
        ->and($model->dataset_name)->toBe('cmp-persist')
        ->and($model->subject_labels)->toBe(['haiku', 'sonnet'])
        ->and($model->total_runs)->toBe(2)
        ->and($model->passed_runs)->toBe(2)
        ->and($model->failed_runs)->toBe(0)
        ->and($model->duration_ms)->toBe(123.0);
});

it('persists one EvalRun per subject linked to the comparison', function (): void {
    $persister = new ComparisonPersister(new EvalPersister);

    $model = $persister->persist(cmpBuildComparison());

    expect(EvalRunModel::query()->count())->toBe(2)
        ->and(EvalRunModel::query()->where('comparison_id', $model->id)->count())->toBe(2);
});

it('stores subject_label on each persisted run', function (): void {
    $persister = new ComparisonPersister(new EvalPersister);

    $model = $persister->persist(cmpBuildComparison());

    $labels = EvalRunModel::query()
        ->where('comparison_id', $model->id)
        ->orderBy('subject_label')
        ->pluck('subject_label')
        ->all();

    expect($labels)->toBe(['haiku', 'sonnet']);
});

it('aggregates total_cost_usd across runs', function (): void {
    $persister = new ComparisonPersister(new EvalPersister);

    $model = $persister->persist(cmpBuildComparison(allPass: true, cost: 0.02));

    expect($model->total_cost_usd)->toBe(0.04);
});

it('leaves total_cost_usd null when no run reports cost', function (): void {
    $persister = new ComparisonPersister(new EvalPersister);

    $model = $persister->persist(cmpBuildComparison(allPass: true, cost: null));

    expect($model->total_cost_usd)->toBeNull();
});

it('links the comparison to the dataset version via first run', function (): void {
    $persister = new ComparisonPersister(new EvalPersister);

    $model = $persister->persist(cmpBuildComparison());

    expect($model->dataset_version_id)->not->toBeNull();
    $version = EvalDatasetVersionModel::query()->firstOrFail();
    expect($model->dataset_version_id)->toBe($version->id);
});

it('wraps persistence in a transaction', function (): void {
    $brokenRunPersister = new class extends EvalPersister
    {
        public int $calls = 0;

        public function persist(
            EvalRun $run,
            ?string $suiteClass = null,
            ?string $commitSha = null,
            ?string $subjectType = null,
            ?string $subjectClass = null,
            ?string $comparisonId = null,
            ?string $subjectLabel = null,
        ): EvalRunModel {
            $this->calls++;
            if ($this->calls >= 2) {
                throw new RuntimeException('synthetic failure on second run');
            }

            return parent::persist($run, $suiteClass, $commitSha, $subjectType, $subjectClass, $comparisonId, $subjectLabel);
        }
    };

    $persister = new ComparisonPersister($brokenRunPersister);

    $caught = null;
    try {
        $persister->persist(cmpBuildComparison());
    } catch (RuntimeException $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(RuntimeException::class)
        ->and(EvalComparisonModel::query()->count())->toBe(0)
        ->and(EvalRunModel::query()->count())->toBe(0);
});

it('stores suite_class and commit_sha when provided', function (): void {
    $persister = new ComparisonPersister(new EvalPersister);

    $model = $persister->persist(
        cmpBuildComparison(),
        suiteClass: 'App\\Suites\\MatrixSuite',
        commitSha: 'deadbeef',
    );

    expect($model->suite_class)->toBe('App\\Suites\\MatrixSuite')
        ->and($model->commit_sha)->toBe('deadbeef');

    $run = EvalRunModel::query()->firstOrFail();
    expect($run->suite_class)->toBe('App\\Suites\\MatrixSuite')
        ->and($run->commit_sha)->toBe('deadbeef');
});

it('counts failed runs in aggregates when a run fails', function (): void {
    $persister = new ComparisonPersister(new EvalPersister);

    $model = $persister->persist(cmpBuildComparison(allPass: false));

    expect($model->passed_runs)->toBe(1)
        ->and($model->failed_runs)->toBe(1);
});

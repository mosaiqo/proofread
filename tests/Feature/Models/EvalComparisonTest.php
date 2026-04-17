<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  array<string, mixed>  $attributes
 */
function newComparison(array $attributes = []): EvalComparison
{
    $defaults = [
        'name' => 'comparison-'.bin2hex(random_bytes(3)),
        'dataset_name' => 'cmp-ds',
        'subject_labels' => ['haiku', 'sonnet'],
        'total_runs' => 0,
        'passed_runs' => 0,
        'failed_runs' => 0,
        'duration_ms' => 0.0,
    ];

    $model = new EvalComparison;
    $model->fill(array_merge($defaults, $attributes));
    $model->save();

    return $model;
}

function newComparisonDataset(string $name = 'cmp-ds'): EvalDataset
{
    $dataset = new EvalDataset;
    $dataset->fill([
        'name' => $name,
        'case_count' => 1,
        'checksum' => hash('sha256', $name),
    ]);
    $dataset->save();

    return $dataset;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function newComparisonRun(EvalDataset $dataset, EvalComparison $comparison, array $overrides = []): EvalRun
{
    $run = new EvalRun;
    $run->fill(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'callable',
        'passed' => true,
        'pass_count' => 3,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 3,
        'duration_ms' => 100.0,
        'comparison_id' => $comparison->id,
    ], $overrides));
    $run->save();

    return $run;
}

it('creates a comparison with ULID primary key', function (): void {
    $comparison = newComparison();

    expect($comparison->id)->toBeString()
        ->and(strlen($comparison->id))->toBe(26);
});

it('persists and retrieves all expected fields', function (): void {
    $dataset = newComparisonDataset('cmp-persist');
    $version = new EvalDatasetVersion;
    $version->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('a', 64),
        'cases' => [['input' => 'x']],
        'case_count' => 1,
        'first_seen_at' => now(),
    ]);
    $version->save();

    $comparison = newComparison([
        'name' => 'persisted',
        'suite_class' => 'App\\Suites\\Foo',
        'dataset_name' => 'cmp-persist',
        'dataset_version_id' => $version->id,
        'subject_labels' => ['haiku', 'sonnet', 'opus'],
        'commit_sha' => 'deadbeef',
        'total_runs' => 3,
        'passed_runs' => 2,
        'failed_runs' => 1,
        'total_cost_usd' => 0.987654,
        'duration_ms' => 1234.567,
    ]);

    $comparison->refresh();

    expect($comparison->name)->toBe('persisted')
        ->and($comparison->suite_class)->toBe('App\\Suites\\Foo')
        ->and($comparison->dataset_name)->toBe('cmp-persist')
        ->and($comparison->dataset_version_id)->toBe($version->id)
        ->and($comparison->subject_labels)->toBe(['haiku', 'sonnet', 'opus'])
        ->and($comparison->commit_sha)->toBe('deadbeef')
        ->and($comparison->total_runs)->toBe(3)
        ->and($comparison->passed_runs)->toBe(2)
        ->and($comparison->failed_runs)->toBe(1)
        ->and($comparison->total_cost_usd)->toBe(0.987654)
        ->and($comparison->duration_ms)->toBe(1234.567);
});

it('casts subject_labels to an array', function (): void {
    $comparison = newComparison(['subject_labels' => ['a', 'b', 'c']]);
    $comparison->refresh();

    expect($comparison->subject_labels)->toBeArray()
        ->and($comparison->subject_labels)->toBe(['a', 'b', 'c']);
});

it('casts numeric fields correctly', function (): void {
    $comparison = newComparison([
        'total_runs' => 4,
        'passed_runs' => 3,
        'failed_runs' => 1,
        'total_cost_usd' => 1.23,
        'duration_ms' => 45.67,
    ]);
    $comparison->refresh();

    expect($comparison->total_runs)->toBeInt()->toBe(4)
        ->and($comparison->passed_runs)->toBeInt()->toBe(3)
        ->and($comparison->failed_runs)->toBeInt()->toBe(1)
        ->and($comparison->total_cost_usd)->toBeFloat()->toBe(1.23)
        ->and($comparison->duration_ms)->toBeFloat()->toBe(45.67);
});

it('belongs to a dataset version', function (): void {
    $dataset = newComparisonDataset('cmp-version-rel');
    $version = new EvalDatasetVersion;
    $version->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('b', 64),
        'cases' => [['input' => 'x']],
        'case_count' => 1,
        'first_seen_at' => now(),
    ]);
    $version->save();

    $comparison = newComparison([
        'dataset_name' => 'cmp-version-rel',
        'dataset_version_id' => $version->id,
    ]);

    expect($comparison->datasetVersion())->toBeInstanceOf(BelongsTo::class)
        ->and($comparison->datasetVersion?->id)->toBe($version->id);
});

it('has many EvalRuns', function (): void {
    $dataset = newComparisonDataset('cmp-has-many');
    $comparison = newComparison(['dataset_name' => 'cmp-has-many']);

    newComparisonRun($dataset, $comparison);
    newComparisonRun($dataset, $comparison);
    newComparisonRun($dataset, $comparison);

    expect($comparison->runs())->toBeInstanceOf(HasMany::class)
        ->and($comparison->runs()->count())->toBe(3);
});

it('returns the run with highest pass rate via bestByPassRate', function (): void {
    $dataset = newComparisonDataset('cmp-best-pass');
    $comparison = newComparison(['dataset_name' => 'cmp-best-pass']);

    newComparisonRun($dataset, $comparison, [
        'subject_label' => 'low',
        'pass_count' => 1, 'fail_count' => 3, 'total_count' => 4, 'passed' => false,
        'duration_ms' => 100.0,
    ]);
    $winner = newComparisonRun($dataset, $comparison, [
        'subject_label' => 'high',
        'pass_count' => 5, 'fail_count' => 0, 'total_count' => 5, 'passed' => true,
        'duration_ms' => 500.0,
    ]);
    newComparisonRun($dataset, $comparison, [
        'subject_label' => 'mid',
        'pass_count' => 3, 'fail_count' => 2, 'total_count' => 5, 'passed' => false,
        'duration_ms' => 200.0,
    ]);

    expect($comparison->bestByPassRate()?->id)->toBe($winner->id);
});

it('uses duration as tiebreaker in bestByPassRate', function (): void {
    $dataset = newComparisonDataset('cmp-tiebreak');
    $comparison = newComparison(['dataset_name' => 'cmp-tiebreak']);

    newComparisonRun($dataset, $comparison, [
        'subject_label' => 'slow',
        'pass_count' => 5, 'fail_count' => 0, 'total_count' => 5, 'passed' => true,
        'duration_ms' => 500.0,
    ]);
    $fast = newComparisonRun($dataset, $comparison, [
        'subject_label' => 'fast',
        'pass_count' => 5, 'fail_count' => 0, 'total_count' => 5, 'passed' => true,
        'duration_ms' => 50.0,
    ]);

    expect($comparison->bestByPassRate()?->id)->toBe($fast->id);
});

it('returns null from bestByPassRate when there are no runs', function (): void {
    $comparison = newComparison();

    expect($comparison->bestByPassRate())->toBeNull();
});

it('returns the cheapest run via cheapest()', function (): void {
    $dataset = newComparisonDataset('cmp-cheap');
    $comparison = newComparison(['dataset_name' => 'cmp-cheap']);

    newComparisonRun($dataset, $comparison, [
        'subject_label' => 'expensive',
        'total_cost_usd' => 1.50,
    ]);
    $cheap = newComparisonRun($dataset, $comparison, [
        'subject_label' => 'cheap',
        'total_cost_usd' => 0.10,
    ]);
    newComparisonRun($dataset, $comparison, [
        'subject_label' => 'null-cost',
        'total_cost_usd' => null,
    ]);

    expect($comparison->cheapest()?->id)->toBe($cheap->id);
});

it('returns null from cheapest when all runs have null cost', function (): void {
    $dataset = newComparisonDataset('cmp-all-null');
    $comparison = newComparison(['dataset_name' => 'cmp-all-null']);

    newComparisonRun($dataset, $comparison, ['total_cost_usd' => null]);
    newComparisonRun($dataset, $comparison, ['total_cost_usd' => null]);

    expect($comparison->cheapest())->toBeNull();
});

it('returns the fastest run via fastest()', function (): void {
    $dataset = newComparisonDataset('cmp-fast');
    $comparison = newComparison(['dataset_name' => 'cmp-fast']);

    newComparisonRun($dataset, $comparison, [
        'subject_label' => 'slow', 'duration_ms' => 900.0,
    ]);
    $fast = newComparisonRun($dataset, $comparison, [
        'subject_label' => 'fast', 'duration_ms' => 10.0,
    ]);
    newComparisonRun($dataset, $comparison, [
        'subject_label' => 'mid', 'duration_ms' => 200.0,
    ]);

    expect($comparison->fastest()?->id)->toBe($fast->id);
});

it('belongs to a dataset via name', function (): void {
    $dataset = newComparisonDataset('cmp-belongs-to');
    $comparison = newComparison(['dataset_name' => 'cmp-belongs-to']);

    expect($comparison->dataset())->toBeInstanceOf(BelongsTo::class)
        ->and($comparison->dataset?->id)->toBe($dataset->id)
        ->and($comparison->dataset?->name)->toBe('cmp-belongs-to');
});

it('returns null dataset when the name does not match', function (): void {
    $comparison = newComparison(['dataset_name' => 'cmp-missing-dataset']);

    expect($comparison->dataset)->toBeNull();
});

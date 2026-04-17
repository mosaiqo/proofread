<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  array<string, mixed>  $attributes
 */
function newDataset(array $attributes): EvalDataset
{
    $dataset = new EvalDataset;
    $dataset->fill($attributes);
    $dataset->save();

    return $dataset;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function newRun(array $attributes): EvalRun
{
    $run = new EvalRun;
    $run->fill($attributes);
    $run->save();

    return $run;
}

it('creates a dataset with a ULID primary key', function (): void {
    $dataset = newDataset([
        'name' => 'greetings',
        'case_count' => 3,
        'checksum' => 'abc123',
    ]);

    expect($dataset->id)->toBeString()
        ->and(strlen($dataset->id))->toBe(26);
});

it('persists and retrieves all expected fields', function (): void {
    $dataset = newDataset([
        'name' => 'greetings',
        'case_count' => 5,
        'checksum' => 'deadbeef',
    ]);

    $dataset->refresh();

    expect($dataset->name)->toBe('greetings')
        ->and($dataset->case_count)->toBe(5)
        ->and($dataset->checksum)->toBe('deadbeef');
});

it('allows a null checksum', function (): void {
    $dataset = newDataset([
        'name' => 'no-checksum',
        'case_count' => 1,
    ]);

    expect($dataset->checksum)->toBeNull();
});

it('exposes a HasMany relation to runs', function (): void {
    $dataset = newDataset([
        'name' => 'with-runs',
        'case_count' => 1,
    ]);

    newRun([
        'dataset_id' => $dataset->id,
        'dataset_name' => 'with-runs',
        'subject_type' => 'callable',
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 10.0,
    ]);

    newRun([
        'dataset_id' => $dataset->id,
        'dataset_name' => 'with-runs',
        'subject_type' => 'callable',
        'passed' => false,
        'pass_count' => 0,
        'fail_count' => 1,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 20.0,
    ]);

    expect($dataset->runs())->toBeInstanceOf(HasMany::class)
        ->and($dataset->runs()->count())->toBe(2);
});

it('casts case_count as integer', function (): void {
    $dataset = newDataset([
        'name' => 'cast-test',
        'case_count' => 7,
    ]);

    $dataset->refresh();

    expect($dataset->case_count)->toBeInt()->toBe(7);
});

it('has many versions', function (): void {
    $dataset = newDataset([
        'name' => 'with-versions',
        'case_count' => 1,
    ]);

    $v1 = new EvalDatasetVersion;
    $v1->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('1', 64),
        'cases' => [['input' => 'a']],
        'case_count' => 1,
        'first_seen_at' => now()->subMinute(),
    ]);
    $v1->save();

    $v2 = new EvalDatasetVersion;
    $v2->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('2', 64),
        'cases' => [['input' => 'a'], ['input' => 'b']],
        'case_count' => 2,
        'first_seen_at' => now(),
    ]);
    $v2->save();

    expect($dataset->versions())->toBeInstanceOf(HasMany::class)
        ->and($dataset->versions()->count())->toBe(2);
});

it('resolves the latest version', function (): void {
    $dataset = newDataset([
        'name' => 'latest-version',
        'case_count' => 1,
    ]);

    $older = new EvalDatasetVersion;
    $older->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('7', 64),
        'cases' => [['input' => 'old']],
        'case_count' => 1,
        'first_seen_at' => now()->subHour(),
    ]);
    $older->save();

    $newer = new EvalDatasetVersion;
    $newer->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('8', 64),
        'cases' => [['input' => 'new']],
        'case_count' => 1,
        'first_seen_at' => now(),
    ]);
    $newer->save();

    expect($dataset->latestVersion())->toBeInstanceOf(HasOne::class)
        ->and($dataset->latestVersion?->id)->toBe($newer->id);
});

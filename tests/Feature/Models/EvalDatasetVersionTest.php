<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  array<string, mixed>  $attributes
 */
function newVersion(array $attributes): EvalDatasetVersion
{
    $version = new EvalDatasetVersion;
    $version->fill($attributes);
    $version->save();

    return $version;
}

function makeDatasetForVersions(string $name = 'ver-ds'): EvalDataset
{
    $dataset = new EvalDataset;
    $dataset->fill([
        'name' => $name,
        'case_count' => 2,
        'checksum' => hash('sha256', $name),
    ]);
    $dataset->save();

    return $dataset;
}

it('creates a version with ULID primary key', function (): void {
    $dataset = makeDatasetForVersions('ver-ulid');

    $version = newVersion([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('a', 64),
        'cases' => [['input' => 'hi']],
        'case_count' => 1,
        'first_seen_at' => now(),
    ]);

    expect($version->id)->toBeString()
        ->and(strlen($version->id))->toBe(26);
});

it('persists and retrieves all expected fields', function (): void {
    $dataset = makeDatasetForVersions('ver-persist');
    $checksum = str_repeat('b', 64);

    $version = newVersion([
        'eval_dataset_id' => $dataset->id,
        'checksum' => $checksum,
        'cases' => [['input' => 'a'], ['input' => 'b']],
        'case_count' => 2,
        'first_seen_at' => now(),
    ]);

    $version->refresh();

    expect($version->eval_dataset_id)->toBe($dataset->id)
        ->and($version->checksum)->toBe($checksum)
        ->and($version->case_count)->toBe(2)
        ->and($version->cases)->toBe([['input' => 'a'], ['input' => 'b']])
        ->and($version->first_seen_at)->not->toBeNull();
});

it('casts cases to an array', function (): void {
    $dataset = makeDatasetForVersions('ver-cast');

    $version = newVersion([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('c', 64),
        'cases' => [['input' => 'x', 'expected' => ['ok' => true]]],
        'case_count' => 1,
        'first_seen_at' => now(),
    ]);

    $version->refresh();

    expect($version->cases)->toBeArray()
        ->and($version->cases[0])->toBe(['input' => 'x', 'expected' => ['ok' => true]]);
});

it('belongs to an EvalDataset', function (): void {
    $dataset = makeDatasetForVersions('ver-rel');
    $version = newVersion([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('d', 64),
        'cases' => [],
        'case_count' => 0,
        'first_seen_at' => now(),
    ]);

    expect($version->dataset())->toBeInstanceOf(BelongsTo::class)
        ->and($version->dataset?->id)->toBe($dataset->id);
});

it('has many EvalRuns', function (): void {
    $dataset = makeDatasetForVersions('ver-runs');
    $version = newVersion([
        'eval_dataset_id' => $dataset->id,
        'checksum' => str_repeat('e', 64),
        'cases' => [],
        'case_count' => 0,
        'first_seen_at' => now(),
    ]);

    $runAttrs = [
        'dataset_id' => $dataset->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'callable',
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 1.0,
        'dataset_version_id' => $version->id,
    ];
    $run1 = new EvalRun;
    $run1->fill($runAttrs);
    $run1->save();

    $run2 = new EvalRun;
    $run2->fill($runAttrs);
    $run2->save();

    expect($version->runs())->toBeInstanceOf(HasMany::class)
        ->and($version->runs()->count())->toBe(2);
});

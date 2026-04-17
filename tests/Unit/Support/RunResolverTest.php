<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalRun;
use Mosaiqo\Proofread\Support\RunResolver;
use Mosaiqo\Proofread\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function seedResolverRun(string $datasetName, array $attributes = []): EvalRun
{
    $dataset = EvalDataset::query()->firstOrCreate(
        ['name' => $datasetName],
        ['case_count' => 1, 'checksum' => hash('sha256', $datasetName)],
    );

    $run = new EvalRun;
    $run->fill(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_name' => $datasetName,
        'suite_class' => null,
        'subject_type' => 'callable',
        'subject_class' => null,
        'commit_sha' => null,
        'model' => null,
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 1.0,
        'total_cost_usd' => null,
        'total_tokens_in' => null,
        'total_tokens_out' => null,
    ], $attributes));
    $run->save();

    return $run;
}

it('resolves a run by its exact ULID', function (): void {
    $run = seedResolverRun('ds-resolver-ulid');

    $resolved = (new RunResolver)->resolve($run->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved?->id)->toBe($run->id);
});

it('returns null when the ULID does not match any run', function (): void {
    seedResolverRun('ds-resolver-ulid-missing');

    $resolved = (new RunResolver)->resolve('01JZZZZZZZZZZZZZZZZZZZZZZZ');

    expect($resolved)->toBeNull();
});

it('resolves by short commit SHA prefix', function (): void {
    seedResolverRun('ds-resolver-sha', ['commit_sha' => 'abc1234deadbeef0000000000000000000000000']);
    $run = seedResolverRun('ds-resolver-sha', ['commit_sha' => 'feed999cafebabe0000000000000000000000000']);

    $resolved = (new RunResolver)->resolve('feed999');

    expect($resolved?->id)->toBe($run->id);
});

it('resolves by a full 40 char commit SHA', function (): void {
    $sha = str_pad('abcd1234', 40, '0');
    $run = seedResolverRun('ds-resolver-sha-full', ['commit_sha' => $sha]);

    $resolved = (new RunResolver)->resolve($sha);

    expect($resolved?->id)->toBe($run->id);
});

it('returns the most recent match when multiple runs share a SHA prefix', function (): void {
    $older = seedResolverRun('ds-resolver-sha-latest', ['commit_sha' => 'abc1234deadbeef0000000000000000000000000']);
    $older->created_at = now()->subMinute();
    $older->save();

    $newer = seedResolverRun('ds-resolver-sha-latest', ['commit_sha' => 'abc1234deadbeef0000000000000000000000000']);
    $newer->created_at = now();
    $newer->save();

    $resolved = (new RunResolver)->resolve('abc1234');

    expect($resolved?->id)->toBe($newer->id);
});

it('resolves "latest" to the most recent run overall', function (): void {
    $older = seedResolverRun('ds-resolver-latest');
    $older->created_at = now()->subHour();
    $older->save();

    $newer = seedResolverRun('ds-resolver-latest');
    $newer->created_at = now();
    $newer->save();

    $resolved = (new RunResolver)->resolve('latest');

    expect($resolved?->id)->toBe($newer->id);
});

it('returns null for "latest" when there are no runs', function (): void {
    $resolved = (new RunResolver)->resolve('latest');

    expect($resolved)->toBeNull();
});

it('returns null for an empty identifier', function (): void {
    seedResolverRun('ds-resolver-empty');

    $resolved = (new RunResolver)->resolve('');

    expect($resolved)->toBeNull();
});

it('returns null for non-existent commit SHA prefixes', function (): void {
    seedResolverRun('ds-resolver-sha-none', ['commit_sha' => 'abc1234deadbeef0000000000000000000000000']);

    $resolved = (new RunResolver)->resolve('fffffff');

    expect($resolved)->toBeNull();
});

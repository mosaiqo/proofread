<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mosaiqo\Proofread\Models\EvalComparison;
use Mosaiqo\Proofread\Support\ComparisonResolver;
use Mosaiqo\Proofread\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function seedResolverComparison(string $name, array $attributes = []): EvalComparison
{
    /** @var EvalComparison $comparison */
    $comparison = EvalComparison::query()->create(array_merge([
        'name' => $name,
        'suite_class' => null,
        'dataset_name' => 'ds-cmp-resolver',
        'dataset_version_id' => null,
        'subject_labels' => ['haiku', 'sonnet'],
        'commit_sha' => null,
        'total_runs' => 2,
        'passed_runs' => 1,
        'failed_runs' => 1,
        'total_cost_usd' => null,
        'duration_ms' => 100.0,
    ], $attributes));

    return $comparison;
}

it('resolves a comparison by its exact ULID', function (): void {
    $comparison = seedResolverComparison('cmp-ulid');

    $resolved = (new ComparisonResolver)->resolve($comparison->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved?->id)->toBe($comparison->id);
});

it('returns null when the ULID does not match any comparison', function (): void {
    seedResolverComparison('cmp-ulid-missing');

    $resolved = (new ComparisonResolver)->resolve('01JZZZZZZZZZZZZZZZZZZZZZZZ');

    expect($resolved)->toBeNull();
});

it('resolves a comparison by short commit SHA prefix', function (): void {
    seedResolverComparison('cmp-sha-older', ['commit_sha' => 'abc1234deadbeef0000000000000000000000000']);
    $newer = seedResolverComparison('cmp-sha-newer', ['commit_sha' => 'feed999cafebabe0000000000000000000000000']);

    $resolved = (new ComparisonResolver)->resolve('feed999');

    expect($resolved?->id)->toBe($newer->id);
});

it('resolves a comparison by a full 40 char commit SHA', function (): void {
    $sha = str_pad('abcd1234', 40, '0');
    $comparison = seedResolverComparison('cmp-sha-full', ['commit_sha' => $sha]);

    $resolved = (new ComparisonResolver)->resolve($sha);

    expect($resolved?->id)->toBe($comparison->id);
});

it('returns the most recent match when multiple comparisons share a SHA prefix', function (): void {
    $older = seedResolverComparison('cmp-sha-latest-older', ['commit_sha' => 'abc1234deadbeef0000000000000000000000000']);
    $older->created_at = now()->subMinute();
    $older->save();

    $newer = seedResolverComparison('cmp-sha-latest-newer', ['commit_sha' => 'abc1234deadbeef0000000000000000000000000']);
    $newer->created_at = now();
    $newer->save();

    $resolved = (new ComparisonResolver)->resolve('abc1234');

    expect($resolved?->id)->toBe($newer->id);
});

it('resolves "latest" to the most recent comparison overall', function (): void {
    $older = seedResolverComparison('cmp-latest-older');
    $older->created_at = now()->subHour();
    $older->save();

    $newer = seedResolverComparison('cmp-latest-newer');
    $newer->created_at = now();
    $newer->save();

    $resolved = (new ComparisonResolver)->resolve('latest');

    expect($resolved?->id)->toBe($newer->id);
});

it('returns null for "latest" when there are no comparisons', function (): void {
    $resolved = (new ComparisonResolver)->resolve('latest');

    expect($resolved)->toBeNull();
});

it('returns null for an empty identifier', function (): void {
    seedResolverComparison('cmp-empty');

    $resolved = (new ComparisonResolver)->resolve('');

    expect($resolved)->toBeNull();
});

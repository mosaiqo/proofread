<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\EvalRun;

/**
 * @param  list<array<string, mixed>>  $cases
 */
function seedDatasetVersion(
    EvalDataset $dataset,
    array $cases,
    ?string $checksum = null,
    ?CarbonInterface $firstSeenAt = null,
): EvalDatasetVersion {
    $version = new EvalDatasetVersion;
    $version->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => $checksum ?? hash('sha256', (string) json_encode($cases)),
        'cases' => $cases,
        'case_count' => count($cases),
        'first_seen_at' => $firstSeenAt ?? now(),
    ]);
    $version->save();

    return $version;
}

function seedDataset(string $name = 'diff-ds'): EvalDataset
{
    $dataset = new EvalDataset;
    $dataset->fill([
        'name' => $name,
        'case_count' => 0,
        'checksum' => null,
    ]);
    $dataset->save();

    return $dataset;
}

it('exits 2 when the dataset does not exist', function (): void {
    $exit = Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'does-not-exist',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('does-not-exist');
});

it('exits 0 with a helpful message when only one version exists', function (): void {
    $dataset = seedDataset('single-version');
    seedDatasetVersion($dataset, [['input' => 'only']]);

    $exit = Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'single-version',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Only one version');
});

it('diffs latest against previous by default', function (): void {
    $dataset = seedDataset('diff-default');
    seedDatasetVersion(
        $dataset,
        [['input' => 'a']],
        checksum: str_repeat('1', 64),
        firstSeenAt: now()->subHour(),
    );
    seedDatasetVersion(
        $dataset,
        [['input' => 'a'], ['input' => 'b']],
        checksum: str_repeat('2', 64),
        firstSeenAt: now(),
    );

    $exit = Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-default',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Added:')
        ->and($output)->toContain('Removed:')
        ->and($output)->toContain('Modified:')
        ->and($output)->toContain('Unchanged:');
});

it('resolves versions by short checksum', function (): void {
    $dataset = seedDataset('diff-shortchecksum');
    $baseChecksum = 'abc1234'.str_repeat('0', 57);
    $headChecksum = 'def5678'.str_repeat('0', 57);
    seedDatasetVersion(
        $dataset,
        [['input' => 'a']],
        checksum: $baseChecksum,
        firstSeenAt: now()->subHour(),
    );
    seedDatasetVersion(
        $dataset,
        [['input' => 'a'], ['input' => 'b']],
        checksum: $headChecksum,
        firstSeenAt: now(),
    );

    $exit = Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-shortchecksum',
        '--base' => 'abc1234',
        '--head' => 'def5678',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('abc1234')
        ->and($output)->toContain('def5678');
});

it('resolves versions by run ULID', function (): void {
    $dataset = seedDataset('diff-by-run');
    $baseVersion = seedDatasetVersion(
        $dataset,
        [['input' => 'a']],
        checksum: str_repeat('a', 64),
        firstSeenAt: now()->subHour(),
    );
    $headVersion = seedDatasetVersion(
        $dataset,
        [['input' => 'a'], ['input' => 'b']],
        checksum: str_repeat('b', 64),
        firstSeenAt: now(),
    );

    $baseRun = new EvalRun;
    $baseRun->fill([
        'dataset_id' => $dataset->id,
        'dataset_version_id' => $baseVersion->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'callable',
        'passed' => true,
        'pass_count' => 1,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 1,
        'duration_ms' => 1.0,
    ]);
    $baseRun->save();

    $headRun = new EvalRun;
    $headRun->fill([
        'dataset_id' => $dataset->id,
        'dataset_version_id' => $headVersion->id,
        'dataset_name' => $dataset->name,
        'subject_type' => 'callable',
        'passed' => true,
        'pass_count' => 2,
        'fail_count' => 0,
        'error_count' => 0,
        'total_count' => 2,
        'duration_ms' => 1.0,
    ]);
    $headRun->save();

    $exit = Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-by-run',
        '--base' => $baseRun->id,
        '--head' => $headRun->id,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain($baseVersion->id)
        ->and($output)->toContain($headVersion->id);
});

it('identifies added cases', function (): void {
    $dataset = seedDataset('diff-added');
    seedDatasetVersion(
        $dataset,
        [['input' => 'a', 'meta' => ['name' => 'first']]],
        checksum: str_repeat('1', 64),
        firstSeenAt: now()->subHour(),
    );
    seedDatasetVersion(
        $dataset,
        [
            ['input' => 'a', 'meta' => ['name' => 'first']],
            ['input' => 'b', 'meta' => ['name' => 'second']],
        ],
        checksum: str_repeat('2', 64),
        firstSeenAt: now(),
    );

    Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-added',
        '--format' => 'json',
    ]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray()
        ->and($decoded['summary']['added'])->toBe(1)
        ->and($decoded['summary']['removed'])->toBe(0);

    /** @var list<array<string, mixed>> $changes */
    $changes = $decoded['changes'];
    $added = collect($changes)->firstWhere('status', 'added') ?? [];

    expect($added)->not->toBe([])
        ->and($added['name'] ?? null)->toBe('second');
});

it('identifies removed cases', function (): void {
    $dataset = seedDataset('diff-removed');
    seedDatasetVersion(
        $dataset,
        [
            ['input' => 'a', 'meta' => ['name' => 'first']],
            ['input' => 'b', 'meta' => ['name' => 'second']],
        ],
        checksum: str_repeat('1', 64),
        firstSeenAt: now()->subHour(),
    );
    seedDatasetVersion(
        $dataset,
        [['input' => 'a', 'meta' => ['name' => 'first']]],
        checksum: str_repeat('2', 64),
        firstSeenAt: now(),
    );

    Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-removed',
        '--format' => 'json',
    ]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['summary']['removed'])->toBe(1);

    /** @var list<array<string, mixed>> $changes */
    $changes = $decoded['changes'];
    $removed = collect($changes)->firstWhere('status', 'removed') ?? [];

    expect($removed)->not->toBe([])
        ->and($removed['name'] ?? null)->toBe('second');
});

it('identifies modified cases', function (): void {
    $dataset = seedDataset('diff-modified');
    seedDatasetVersion(
        $dataset,
        [['input' => 'original', 'meta' => ['name' => 'same-name']]],
        checksum: str_repeat('1', 64),
        firstSeenAt: now()->subHour(),
    );
    seedDatasetVersion(
        $dataset,
        [['input' => 'updated', 'meta' => ['name' => 'same-name']]],
        checksum: str_repeat('2', 64),
        firstSeenAt: now(),
    );

    Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-modified',
        '--format' => 'json',
    ]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['summary']['modified'])->toBe(1);

    /** @var list<array<string, mixed>> $changes */
    $changes = $decoded['changes'];
    $modified = collect($changes)->firstWhere('status', 'modified') ?? [];

    expect($modified)->not->toBe([])
        ->and($modified['name'] ?? null)->toBe('same-name');
});

it('identifies unchanged cases', function (): void {
    $dataset = seedDataset('diff-unchanged');
    $cases = [['input' => 'stable', 'meta' => ['name' => 'stable-case']]];
    seedDatasetVersion(
        $dataset,
        $cases,
        checksum: str_repeat('1', 64),
        firstSeenAt: now()->subHour(),
    );
    $newCases = $cases;
    $newCases[] = ['input' => 'new', 'meta' => ['name' => 'new-case']];
    seedDatasetVersion(
        $dataset,
        $newCases,
        checksum: str_repeat('2', 64),
        firstSeenAt: now(),
    );

    Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-unchanged',
        '--format' => 'json',
    ]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['summary']['unchanged'])->toBe(1)
        ->and($decoded['summary']['added'])->toBe(1);
});

it('outputs table format by default', function (): void {
    $dataset = seedDataset('diff-table');
    seedDatasetVersion(
        $dataset,
        [['input' => 'a', 'meta' => ['name' => 'first']]],
        checksum: str_repeat('1', 64),
        firstSeenAt: now()->subHour(),
    );
    seedDatasetVersion(
        $dataset,
        [
            ['input' => 'a', 'meta' => ['name' => 'first']],
            ['input' => 'b', 'meta' => ['name' => 'second']],
        ],
        checksum: str_repeat('2', 64),
        firstSeenAt: now(),
    );

    Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-table',
    ]);

    $output = Artisan::output();

    expect($output)->toContain('Summary:')
        ->and($output)->toContain('Added:')
        ->and($output)->toContain('[+]');
});

it('outputs JSON with --format=json', function (): void {
    $dataset = seedDataset('diff-json');
    seedDatasetVersion(
        $dataset,
        [['input' => 'a']],
        checksum: str_repeat('1', 64),
        firstSeenAt: now()->subHour(),
    );
    seedDatasetVersion(
        $dataset,
        [['input' => 'a'], ['input' => 'b']],
        checksum: str_repeat('2', 64),
        firstSeenAt: now(),
    );

    Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-json',
        '--format' => 'json',
    ]);

    $decoded = json_decode(Artisan::output(), true);

    expect($decoded)->toBeArray()
        ->and($decoded['dataset'])->toBe('diff-json')
        ->and($decoded['base'])->toBeArray()
        ->and($decoded['head'])->toBeArray()
        ->and($decoded['summary'])->toBeArray()
        ->and($decoded['changes'])->toBeArray();
});

it('includes base and head metadata in the header', function (): void {
    $dataset = seedDataset('diff-header');
    $base = seedDatasetVersion(
        $dataset,
        [['input' => 'a']],
        checksum: str_repeat('1', 64),
        firstSeenAt: now()->subHour(),
    );
    $head = seedDatasetVersion(
        $dataset,
        [['input' => 'a'], ['input' => 'b']],
        checksum: str_repeat('2', 64),
        firstSeenAt: now(),
    );

    Artisan::call('evals:dataset:diff', [
        'dataset_name' => 'diff-header',
    ]);

    $output = Artisan::output();

    expect($output)->toContain('base:')
        ->and($output)->toContain('head:')
        ->and($output)->toContain($base->id)
        ->and($output)->toContain($head->id)
        ->and($output)->toContain('diff-header');
});

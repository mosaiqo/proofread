<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;

function proofread_tmp_export_dir(): string
{
    $dir = sys_get_temp_dir().'/proofread-export-'.bin2hex(random_bytes(4));
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

afterEach(function (): void {
    $base = sys_get_temp_dir();
    foreach (glob($base.'/proofread-export-*') ?: [] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
});

/**
 * @param  list<array<string, mixed>>  $cases
 */
function proofread_seed_dataset_with_version(string $name, array $cases, string $checksum, ?Carbon $seenAt = null): EvalDatasetVersion
{
    $seenAt = $seenAt ?? Carbon::now();

    $dataset = EvalDataset::query()->where('name', $name)->first();
    if ($dataset === null) {
        $dataset = new EvalDataset;
        $dataset->fill([
            'name' => $name,
            'case_count' => count($cases),
            'checksum' => $checksum,
        ]);
        $dataset->save();
    }

    $version = new EvalDatasetVersion;
    $version->fill([
        'eval_dataset_id' => $dataset->id,
        'checksum' => $checksum,
        'cases' => $cases,
        'case_count' => count($cases),
        'first_seen_at' => $seenAt,
    ]);
    $version->save();

    return $version;
}

it('exports a dataset as JSON', function (): void {
    proofread_seed_dataset_with_version('demo', [
        ['input' => 'hi', 'expected' => 'hello'],
        ['input' => 'bye', 'expected' => 'farewell'],
    ], 'abc1234');

    $dir = proofread_tmp_export_dir();
    $out = $dir.'/demo.json';

    $exit = Artisan::call('dataset:export', [
        'dataset' => 'demo',
        '--format' => 'json',
        '--output' => $out,
    ]);

    expect($exit)->toBe(0)
        ->and(file_exists($out))->toBeTrue();

    $decoded = json_decode((string) file_get_contents($out), true);
    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveCount(2)
        ->and($decoded[0]['input'])->toBe('hi');
});

it('exports a dataset as CSV', function (): void {
    proofread_seed_dataset_with_version('csv-export', [
        ['input' => 'hi', 'expected' => 'hello'],
        ['input' => 'bye', 'expected' => 'farewell'],
    ], 'def5678');

    $dir = proofread_tmp_export_dir();
    $out = $dir.'/csv-export.csv';

    $exit = Artisan::call('dataset:export', [
        'dataset' => 'csv-export',
        '--format' => 'csv',
        '--output' => $out,
    ]);

    expect($exit)->toBe(0)
        ->and(file_exists($out))->toBeTrue();

    $contents = (string) file_get_contents($out);
    expect($contents)->toContain('input,expected')
        ->and($contents)->toContain('hi,hello')
        ->and($contents)->toContain('bye,farewell');
});

it('resolves version by short checksum', function (): void {
    $first = proofread_seed_dataset_with_version(
        'versioned',
        [['input' => 'v1']],
        'aaa1111deadbeef',
        Carbon::now()->subMinute()
    );
    $second = proofread_seed_dataset_with_version(
        'versioned',
        [['input' => 'v2']],
        'bbb2222cafebabe',
    );

    $dir = proofread_tmp_export_dir();
    $out = $dir.'/v1.json';

    $exit = Artisan::call('dataset:export', [
        'dataset' => 'versioned',
        '--format' => 'json',
        '--output' => $out,
        '--dataset-version' => 'aaa1111',
    ]);

    expect($exit)->toBe(0);

    $decoded = json_decode((string) file_get_contents($out), true);
    expect($decoded[0]['input'])->toBe('v1');

    unset($first, $second);
});

it('defaults to latest version', function (): void {
    proofread_seed_dataset_with_version(
        'latest-default',
        [['input' => 'old']],
        'olddigest',
        Carbon::now()->subHour(),
    );
    proofread_seed_dataset_with_version(
        'latest-default',
        [['input' => 'new']],
        'newdigest',
    );

    $dir = proofread_tmp_export_dir();
    $out = $dir.'/latest.json';

    Artisan::call('dataset:export', [
        'dataset' => 'latest-default',
        '--format' => 'json',
        '--output' => $out,
    ]);

    $decoded = json_decode((string) file_get_contents($out), true);
    expect($decoded[0]['input'])->toBe('new');
});

it('exits 2 when dataset does not exist', function (): void {
    $exit = Artisan::call('dataset:export', [
        'dataset' => 'nonexistent',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('nonexistent');
});

it('exits 2 when version identifier is ambiguous or missing', function (): void {
    proofread_seed_dataset_with_version('v-miss', [['input' => 'a']], 'abcdef1234');

    $exit = Artisan::call('dataset:export', [
        'dataset' => 'v-miss',
        '--dataset-version' => 'zzzz999',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('version');
});

it('writes to --output path when provided', function (): void {
    proofread_seed_dataset_with_version('with-output', [['input' => 'hi']], 'checksum-xx');

    $dir = proofread_tmp_export_dir();
    $out = $dir.'/payload.json';

    Artisan::call('dataset:export', [
        'dataset' => 'with-output',
        '--format' => 'json',
        '--output' => $out,
    ]);

    expect(file_exists($out))->toBeTrue();
});

it('prints to stdout when --output is absent', function (): void {
    proofread_seed_dataset_with_version('stdout-target', [['input' => 'hi-stdout']], 'ck1234567');

    Artisan::call('dataset:export', [
        'dataset' => 'stdout-target',
        '--format' => 'json',
    ]);

    $output = Artisan::output();

    expect($output)->toContain('hi-stdout');
});

it('flattens nested meta into meta_* columns in CSV', function (): void {
    proofread_seed_dataset_with_version('csv-meta', [
        ['input' => 'hello', 'expected' => 'greeting', 'meta' => ['name' => 'greeting-case', 'priority' => 'high']],
    ], 'csvmetadigest');

    $dir = proofread_tmp_export_dir();
    $out = $dir.'/csv-meta.csv';

    Artisan::call('dataset:export', [
        'dataset' => 'csv-meta',
        '--format' => 'csv',
        '--output' => $out,
    ]);

    $contents = (string) file_get_contents($out);
    expect($contents)->toContain('meta_name')
        ->and($contents)->toContain('meta_priority')
        ->and($contents)->toContain('greeting-case')
        ->and($contents)->toContain('high');
});

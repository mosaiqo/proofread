<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Models\EvalDataset;
use Mosaiqo\Proofread\Models\EvalDatasetVersion;
use Mosaiqo\Proofread\Models\EvalResult;
use Mosaiqo\Proofread\Models\EvalRun;

uses(RefreshDatabase::class);

/**
 * @param  list<array<string, mixed>>  $resultsData
 * @param  array<string, mixed>  $runAttributes
 */
function seedExportRun(
    string $datasetName,
    array $resultsData,
    array $runAttributes = [],
    ?string $datasetVersionChecksum = null,
): EvalRun {
    $dataset = EvalDataset::query()->firstOrCreate(
        ['name' => $datasetName],
        ['case_count' => count($resultsData), 'checksum' => hash('sha256', $datasetName)],
    );

    $versionId = null;
    if ($datasetVersionChecksum !== null) {
        $version = new EvalDatasetVersion;
        $version->fill([
            'eval_dataset_id' => $dataset->id,
            'checksum' => $datasetVersionChecksum,
            'cases' => [],
            'case_count' => count($resultsData),
            'first_seen_at' => now(),
        ]);
        $version->save();
        $versionId = $version->id;
    }

    $passCount = 0;
    $failCount = 0;
    $errorCount = 0;
    foreach ($resultsData as $row) {
        if (isset($row['error_class'])) {
            $errorCount++;
            $failCount++;

            continue;
        }
        if (($row['passed'] ?? true) === true) {
            $passCount++;
        } else {
            $failCount++;
        }
    }

    $run = new EvalRun;
    $run->fill(array_merge([
        'dataset_id' => $dataset->id,
        'dataset_version_id' => $versionId,
        'dataset_name' => $datasetName,
        'suite_class' => null,
        'subject_type' => 'callable',
        'subject_class' => null,
        'commit_sha' => null,
        'model' => null,
        'passed' => $failCount === 0,
        'pass_count' => $passCount,
        'fail_count' => $failCount,
        'error_count' => $errorCount,
        'total_count' => count($resultsData),
        'duration_ms' => 12.5,
        'total_cost_usd' => null,
        'total_tokens_in' => null,
        'total_tokens_out' => null,
    ], $runAttributes));
    $run->save();

    foreach ($resultsData as $row) {
        $result = new EvalResult;
        $result->fill([
            'run_id' => $run->id,
            'case_index' => $row['case_index'],
            'case_name' => $row['case_name'] ?? null,
            'input' => $row['input'] ?? ['value' => 'x'],
            'output' => $row['output'] ?? null,
            'expected' => $row['expected'] ?? null,
            'passed' => $row['passed'] ?? true,
            'assertion_results' => $row['assertion_results'] ?? [],
            'error_class' => $row['error_class'] ?? null,
            'error_message' => $row['error_message'] ?? null,
            'error_trace' => null,
            'duration_ms' => $row['duration_ms'] ?? 1.0,
            'latency_ms' => null,
            'tokens_in' => null,
            'tokens_out' => null,
            'cost_usd' => $row['cost_usd'] ?? null,
            'model' => null,
        ]);
        $result->save();
    }

    return $run->fresh() ?? $run;
}

function tempExportDir(): string
{
    $base = sys_get_temp_dir().'/proofread-export-'.bin2hex(random_bytes(6));
    if (! mkdir($base, 0755, true) && ! is_dir($base)) {
        throw new RuntimeException('Failed to create temp dir: '.$base);
    }

    return $base;
}

function removeExportDir(string $path): void
{
    if (! is_dir($path)) {
        if (is_file($path)) {
            @unlink($path);
        }

        return;
    }
    $entries = scandir($path);
    if ($entries === false) {
        return;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $full = $path.'/'.$entry;
        if (is_dir($full)) {
            removeExportDir($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($path);
}

it('resolves a run by ULID and outputs markdown to stdout', function (): void {
    $run = seedExportRun('ds-export-ulid', [
        [
            'case_index' => 0,
            'case_name' => 'greeting',
            'input' => ['prompt' => 'hi'],
            'output' => 'hello',
            'passed' => true,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => 'found'],
            ],
        ],
    ]);

    $exit = Artisan::call('evals:export', ['run' => $run->id]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('# Eval Run: ds-export-ulid')
        ->and($output)->toContain('PASSED')
        ->and($output)->toContain('greeting')
        ->and($output)->toContain($run->id);
});

it('resolves by short commit SHA', function (): void {
    $run = seedExportRun(
        'ds-export-sha',
        [['case_index' => 0, 'passed' => true]],
        ['commit_sha' => 'cafebabe0123456789'],
    );

    $exit = Artisan::call('evals:export', ['run' => 'cafebab']);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain($run->id)
        ->and($output)->toContain('ds-export-sha');
});

it('resolves by "latest" keyword', function (): void {
    $older = seedExportRun('ds-export-latest', [['case_index' => 0, 'passed' => true]]);
    $older->created_at = now()->subHour();
    $older->save();

    $newer = seedExportRun('ds-export-latest', [['case_index' => 0, 'passed' => true]]);
    $newer->created_at = now();
    $newer->save();

    Artisan::call('evals:export', ['run' => 'latest']);

    $output = Artisan::output();

    expect($output)->toContain($newer->id)
        ->and($output)->not->toContain($older->id);
});

it('exits 2 when the run is not found', function (): void {
    $exit = Artisan::call('evals:export', ['run' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('Could not resolve');
});

it('outputs HTML when --format=html', function (): void {
    $run = seedExportRun('ds-export-html', [
        [
            'case_index' => 0,
            'case_name' => 'html-case',
            'passed' => true,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => true, 'reason' => 'ok'],
            ],
        ],
    ]);

    Artisan::call('evals:export', [
        'run' => $run->id,
        '--format' => 'html',
    ]);

    $output = Artisan::output();

    expect($output)->toContain('<html')
        ->and($output)->toContain('<style')
        ->and($output)->toContain('ds-export-html')
        ->and($output)->toContain('html-case');
});

it('writes to a file when --output is provided', function (): void {
    $run = seedExportRun('ds-export-outfile', [['case_index' => 0, 'passed' => true]]);
    $dir = tempExportDir();
    $path = $dir.'/run.md';

    try {
        Artisan::call('evals:export', [
            'run' => $run->id,
            '--output' => $path,
        ]);

        $stdout = Artisan::output();
        $fileContents = (string) file_get_contents($path);

        expect(file_exists($path))->toBeTrue()
            ->and($fileContents)->toContain('ds-export-outfile')
            ->and($stdout)->not->toContain('# Eval Run:')
            ->and($stdout)->toContain($path);
    } finally {
        removeExportDir($dir);
    }
});

it('creates parent directories for --output', function (): void {
    $run = seedExportRun('ds-export-mkdir', [['case_index' => 0, 'passed' => true]]);
    $dir = tempExportDir();
    $path = $dir.'/nested/deep/run.md';

    try {
        Artisan::call('evals:export', [
            'run' => $run->id,
            '--output' => $path,
        ]);

        expect(is_dir($dir.'/nested/deep'))->toBeTrue()
            ->and(file_exists($path))->toBeTrue();
    } finally {
        removeExportDir($dir);
    }
});

it('overwrites an existing output file atomically', function (): void {
    $run = seedExportRun('ds-export-overwrite', [['case_index' => 0, 'passed' => true]]);
    $dir = tempExportDir();
    $path = $dir.'/run.md';
    file_put_contents($path, 'stale');

    try {
        Artisan::call('evals:export', [
            'run' => $run->id,
            '--output' => $path,
        ]);

        $contents = (string) file_get_contents($path);

        expect($contents)->not->toBe('stale')
            ->and($contents)->toContain('ds-export-overwrite');

        $entries = array_values(array_filter(
            scandir($dir) ?: [],
            fn (string $e): bool => $e !== '.' && $e !== '..',
        ));
        expect($entries)->toBe(['run.md']);
    } finally {
        removeExportDir($dir);
    }
});

it('truncates long inputs in the output', function (): void {
    $longInput = str_repeat('A', 2000);
    $run = seedExportRun('ds-export-truncate', [
        [
            'case_index' => 0,
            'case_name' => 'big',
            'input' => ['blob' => $longInput],
            'output' => str_repeat('B', 2000),
            'passed' => true,
        ],
    ]);

    Artisan::call('evals:export', ['run' => $run->id]);

    $output = Artisan::output();

    expect($output)->toContain('...')
        ->and(substr_count($output, 'A'))->toBeLessThan(2000)
        ->and(substr_count($output, 'B'))->toBeLessThan(2000);
});

it('includes all case results in the output', function (): void {
    $run = seedExportRun('ds-export-allcases', [
        ['case_index' => 0, 'case_name' => 'alpha', 'passed' => true],
        ['case_index' => 1, 'case_name' => 'beta', 'passed' => false],
        ['case_index' => 2, 'case_name' => 'gamma', 'passed' => true],
    ]);

    Artisan::call('evals:export', ['run' => $run->id]);

    $output = Artisan::output();

    expect($output)->toContain('alpha')
        ->and($output)->toContain('beta')
        ->and($output)->toContain('gamma');
});

it('includes the dataset version checksum when available', function (): void {
    $checksum = str_repeat('1', 64);
    $run = seedExportRun(
        'ds-export-version',
        [['case_index' => 0, 'passed' => true]],
        [],
        $checksum,
    );

    Artisan::call('evals:export', ['run' => $run->id]);

    $output = Artisan::output();

    expect($output)->toContain(substr($checksum, 0, 12));
});

it('omits cost and tokens when null', function (): void {
    $run = seedExportRun('ds-export-nullcost', [['case_index' => 0, 'passed' => true]]);

    Artisan::call('evals:export', ['run' => $run->id]);

    $output = Artisan::output();

    expect($output)->not->toContain('Total cost:')
        ->and($output)->not->toContain('Tokens in/out:');
});

it('includes an error block for cases that raised', function (): void {
    $run = seedExportRun('ds-export-error', [
        [
            'case_index' => 0,
            'case_name' => 'boom',
            'error_class' => 'RuntimeException',
            'error_message' => 'kaboom',
            'passed' => false,
        ],
    ]);

    Artisan::call('evals:export', ['run' => $run->id]);

    $output = Artisan::output();

    expect($output)->toContain('RuntimeException')
        ->and($output)->toContain('kaboom');
});

it('includes assertion details with pass/fail and reason', function (): void {
    $run = seedExportRun('ds-export-assertions', [
        [
            'case_index' => 0,
            'case_name' => 'c1',
            'passed' => false,
            'assertion_results' => [
                ['name' => 'contains', 'passed' => false, 'reason' => 'missing needle'],
                ['name' => 'regex', 'passed' => true, 'reason' => 'matched'],
            ],
        ],
    ]);

    Artisan::call('evals:export', ['run' => $run->id]);

    $output = Artisan::output();

    expect($output)->toContain('contains')
        ->and($output)->toContain('missing needle')
        ->and($output)->toContain('regex')
        ->and($output)->toContain('matched');
});

it('uses a valid markdown structure', function (): void {
    $run = seedExportRun('ds-export-structure', [
        ['case_index' => 0, 'case_name' => 'c', 'passed' => true],
    ]);

    Artisan::call('evals:export', ['run' => $run->id]);

    $output = Artisan::output();

    expect($output)->toMatch('/^# Eval Run:/m')
        ->and($output)->toContain('## Summary')
        ->and($output)->toContain('## Cases')
        ->and($output)->toMatch('/^\|/m');
});

it('uses self-contained HTML without external links', function (): void {
    $run = seedExportRun('ds-export-selfcontained', [
        ['case_index' => 0, 'case_name' => 'c', 'passed' => true],
    ]);

    Artisan::call('evals:export', [
        'run' => $run->id,
        '--format' => 'html',
    ]);

    $output = Artisan::output();

    expect($output)->not->toMatch('/<link[^>]+rel=["\']stylesheet/i')
        ->and($output)->not->toMatch('/<script[^>]+src=["\']https?:\/\//i');
});

it('rejects unsupported --format values', function (): void {
    $run = seedExportRun('ds-export-badformat', [['case_index' => 0, 'passed' => true]]);

    $exit = Artisan::call('evals:export', [
        'run' => $run->id,
        '--format' => 'pdf',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('Unsupported --format');
});

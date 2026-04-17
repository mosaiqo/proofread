<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function proofread_tmp_import_dir(): string
{
    $dir = sys_get_temp_dir().'/proofread-import-'.bin2hex(random_bytes(4));
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

afterEach(function (): void {
    $base = sys_get_temp_dir();
    foreach (glob($base.'/proofread-import-*') ?: [] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
});

it('imports a CSV file into a PHP dataset file', function (): void {
    $dir = proofread_tmp_import_dir();
    $csvPath = $dir.'/sample.csv';
    file_put_contents($csvPath, "input,expected\nhello,greeting\nworld,place\nfoo,bar\n");

    $outputPath = $dir.'/sample-dataset.php';

    $exit = Artisan::call('dataset:import', [
        'file' => $csvPath,
        '--output' => $outputPath,
    ]);

    expect($exit)->toBe(0)
        ->and(file_exists($outputPath))->toBeTrue();

    $loaded = require $outputPath;
    expect($loaded)->toBeArray()
        ->and($loaded)->toHaveCount(3)
        ->and($loaded[0]['input'])->toBe('hello')
        ->and($loaded[0]['expected'])->toBe('greeting');
});

it('imports a JSON file', function (): void {
    $dir = proofread_tmp_import_dir();
    $jsonPath = $dir.'/sample.json';
    file_put_contents($jsonPath, (string) json_encode([
        ['input' => 'hello', 'expected' => 'greeting'],
        ['input' => 'world', 'expected' => 'place'],
    ]));

    $outputPath = $dir.'/sample-dataset.php';

    $exit = Artisan::call('dataset:import', [
        'file' => $jsonPath,
        '--output' => $outputPath,
    ]);

    expect($exit)->toBe(0)
        ->and(file_exists($outputPath))->toBeTrue();

    $loaded = require $outputPath;
    expect($loaded)->toHaveCount(2)
        ->and($loaded[0]['input'])->toBe('hello');
});

it('supports nested meta in JSON', function (): void {
    $dir = proofread_tmp_import_dir();
    $jsonPath = $dir.'/with-meta.json';
    file_put_contents($jsonPath, (string) json_encode([
        ['input' => 'hello', 'meta' => ['name' => 'greeting-case', 'priority' => 'high']],
    ]));

    $outputPath = $dir.'/meta-dataset.php';

    $exit = Artisan::call('dataset:import', [
        'file' => $jsonPath,
        '--output' => $outputPath,
    ]);

    expect($exit)->toBe(0);

    $loaded = require $outputPath;
    expect($loaded[0]['meta']['name'])->toBe('greeting-case')
        ->and($loaded[0]['meta']['priority'])->toBe('high');
});

it('flattens meta_* columns from CSV into meta array', function (): void {
    $dir = proofread_tmp_import_dir();
    $csvPath = $dir.'/flat-meta.csv';
    file_put_contents($csvPath, "input,expected,meta_name,meta_priority\nhello,greeting,greeting-case,high\n");

    $outputPath = $dir.'/flat-dataset.php';

    $exit = Artisan::call('dataset:import', [
        'file' => $csvPath,
        '--output' => $outputPath,
    ]);

    expect($exit)->toBe(0);

    $loaded = require $outputPath;
    expect($loaded[0])->toHaveKey('meta')
        ->and($loaded[0]['meta']['name'])->toBe('greeting-case')
        ->and($loaded[0]['meta']['priority'])->toBe('high');
});

it('uses basename as default name', function (): void {
    $dir = proofread_tmp_import_dir();
    $csvPath = $dir.'/my-greetings.csv';
    file_put_contents($csvPath, "input\nhello\n");

    $outputPath = $dir.'/my-greetings-dataset.php';

    $exit = Artisan::call('dataset:import', [
        'file' => $csvPath,
        '--output' => $outputPath,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('my-greetings');
});

it('respects --name override', function (): void {
    $dir = proofread_tmp_import_dir();
    $csvPath = $dir.'/whatever.csv';
    file_put_contents($csvPath, "input\nhello\n");

    $outputPath = $dir.'/out.php';

    $exit = Artisan::call('dataset:import', [
        'file' => $csvPath,
        '--name' => 'custom-name',
        '--output' => $outputPath,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('custom-name');
});

it('refuses to overwrite existing output without --force', function (): void {
    $dir = proofread_tmp_import_dir();
    $csvPath = $dir.'/a.csv';
    file_put_contents($csvPath, "input\nhello\n");

    $outputPath = $dir.'/existing.php';
    file_put_contents($outputPath, '<?php return [];');

    $exit = Artisan::call('dataset:import', [
        'file' => $csvPath,
        '--output' => $outputPath,
    ]);

    expect($exit)->toBe(2);

    $exitForced = Artisan::call('dataset:import', [
        'file' => $csvPath,
        '--output' => $outputPath,
        '--force' => true,
    ]);

    expect($exitForced)->toBe(0);
});

it('exits 2 on unsupported file extension', function (): void {
    $dir = proofread_tmp_import_dir();
    $path = $dir.'/data.yaml';
    file_put_contents($path, 'input: hello');

    $exit = Artisan::call('dataset:import', [
        'file' => $path,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('Unsupported');
});

it('exits 2 when input file does not exist', function (): void {
    $exit = Artisan::call('dataset:import', [
        'file' => '/nonexistent/path/to/missing.json',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('not found');
});

it('exits 2 when JSON is invalid', function (): void {
    $dir = proofread_tmp_import_dir();
    $path = $dir.'/bad.json';
    file_put_contents($path, 'not valid json {');

    $exit = Artisan::call('dataset:import', [
        'file' => $path,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('JSON');
});

it('exits 2 when CSV lacks input column', function (): void {
    $dir = proofread_tmp_import_dir();
    $path = $dir.'/no-input.csv';
    file_put_contents($path, "name,value\nfoo,bar\n");

    $exit = Artisan::call('dataset:import', [
        'file' => $path,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(2)
        ->and($output)->toContain('input');
});

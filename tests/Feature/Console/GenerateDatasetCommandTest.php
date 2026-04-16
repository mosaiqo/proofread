<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Generator\DatasetGeneratorAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\EchoAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\StructuredClassifierAgent;

beforeEach(function (): void {
    config()->set('ai.default', 'openai');
});

function proofread_tmp_gen_dir(): string
{
    $dir = sys_get_temp_dir().'/proofread-gen-'.bin2hex(random_bytes(4));
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

afterEach(function (): void {
    $base = sys_get_temp_dir();
    foreach (glob($base.'/proofread-gen-*') ?: [] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
});

it('generates from agent schema', function (): void {
    $payload = json_encode([
        ['input' => ['sentiment' => 'positive'], 'meta' => ['name' => 'a']],
        ['input' => ['sentiment' => 'negative'], 'meta' => ['name' => 'b']],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $exit = Artisan::call('dataset:generate', [
        '--agent' => StructuredClassifierAgent::class,
        '--criteria' => 'sample sentiment cases',
        '--count' => 2,
        '--format' => 'json',
    ]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('"sentiment"');
});

it('generates from schema file', function (): void {
    $dir = proofread_tmp_gen_dir();
    $schemaPath = $dir.'/schema.json';
    file_put_contents($schemaPath, (string) json_encode(['type' => 'string']));

    $payload = json_encode([
        ['input' => 'hi'],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $exit = Artisan::call('dataset:generate', [
        '--schema' => $schemaPath,
        '--criteria' => 'simple cases',
        '--count' => 1,
        '--format' => 'json',
    ]);

    expect($exit)->toBe(0);
});

it('requires either agent or schema', function (): void {
    $exit = Artisan::call('dataset:generate', [
        '--criteria' => 'x',
    ]);

    expect($exit)->toBe(2)
        ->and(Artisan::output())->toContain('--agent');
});

it('rejects both agent and schema', function (): void {
    $dir = proofread_tmp_gen_dir();
    $schemaPath = $dir.'/schema.json';
    file_put_contents($schemaPath, (string) json_encode(['type' => 'string']));

    $exit = Artisan::call('dataset:generate', [
        '--agent' => StructuredClassifierAgent::class,
        '--schema' => $schemaPath,
        '--criteria' => 'x',
    ]);

    expect($exit)->toBe(2)
        ->and(Artisan::output())->toContain('mutually exclusive');
});

it('requires criteria', function (): void {
    $exit = Artisan::call('dataset:generate', [
        '--agent' => StructuredClassifierAgent::class,
    ]);

    expect($exit)->toBe(2)
        ->and(Artisan::output())->toContain('--criteria');
});

it('rejects count below 1', function (): void {
    $exit = Artisan::call('dataset:generate', [
        '--agent' => StructuredClassifierAgent::class,
        '--criteria' => 'x',
        '--count' => 0,
    ]);

    expect($exit)->toBe(2)
        ->and(Artisan::output())->toContain('between 1 and 100');
});

it('rejects count above 100', function (): void {
    $exit = Artisan::call('dataset:generate', [
        '--agent' => StructuredClassifierAgent::class,
        '--criteria' => 'x',
        '--count' => 101,
    ]);

    expect($exit)->toBe(2)
        ->and(Artisan::output())->toContain('between 1 and 100');
});

it('rejects an agent that does not implement HasStructuredOutput', function (): void {
    $exit = Artisan::call('dataset:generate', [
        '--agent' => EchoAgent::class,
        '--criteria' => 'x',
    ]);

    expect($exit)->toBe(2);
});

it('prints php array to stdout', function (): void {
    $payload = json_encode([
        ['input' => 'alpha', 'meta' => ['name' => 'a']],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $exit = Artisan::call('dataset:generate', [
        '--schema' => (function (): string {
            $dir = proofread_tmp_gen_dir();
            $p = $dir.'/s.json';
            file_put_contents($p, (string) json_encode(['type' => 'string']));

            return $p;
        })(),
        '--criteria' => 'c',
        '--count' => 1,
        '--format' => 'php',
    ]);

    $out = Artisan::output();

    expect($exit)->toBe(0)
        ->and($out)->toContain('<?php')
        ->and($out)->toContain("'input' => 'alpha'")
        ->and($out)->toContain('return [');
});

it('prints JSON to stdout', function (): void {
    $payload = json_encode([
        ['input' => 'x'],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $dir = proofread_tmp_gen_dir();
    $schemaPath = $dir.'/s.json';
    file_put_contents($schemaPath, (string) json_encode(['type' => 'string']));

    $exit = Artisan::call('dataset:generate', [
        '--schema' => $schemaPath,
        '--criteria' => 'c',
        '--count' => 1,
        '--format' => 'json',
    ]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('"input": "x"');
});

it('writes output to a file', function (): void {
    $payload = json_encode([
        ['input' => 'foo', 'meta' => ['name' => 'f']],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $dir = proofread_tmp_gen_dir();
    $schemaPath = $dir.'/s.json';
    file_put_contents($schemaPath, (string) json_encode(['type' => 'string']));
    $outputPath = $dir.'/out.php';

    $exit = Artisan::call('dataset:generate', [
        '--schema' => $schemaPath,
        '--criteria' => 'c',
        '--count' => 1,
        '--format' => 'php',
        '--output' => $outputPath,
    ]);

    expect($exit)->toBe(0)
        ->and(is_file($outputPath))->toBeTrue();

    $contents = (string) file_get_contents($outputPath);
    expect($contents)->toContain("'input' => 'foo'");

    $loaded = require $outputPath;
    expect($loaded)->toBeArray()->toHaveCount(1)
        ->and($loaded[0]['input'])->toBe('foo');
});

it('appends to an existing file', function (): void {
    $existing = [
        ['input' => 'existing-1', 'meta' => ['name' => 'e1']],
    ];
    $payload = json_encode([
        ['input' => 'new-1', 'meta' => ['name' => 'n1']],
        ['input' => 'new-2', 'meta' => ['name' => 'n2']],
    ], JSON_UNESCAPED_UNICODE);

    DatasetGeneratorAgent::fake([(string) $payload]);

    $dir = proofread_tmp_gen_dir();
    $schemaPath = $dir.'/s.json';
    file_put_contents($schemaPath, (string) json_encode(['type' => 'string']));
    $outputPath = $dir.'/out.php';
    file_put_contents($outputPath, "<?php\n\nreturn ".var_export($existing, true).";\n");

    $exit = Artisan::call('dataset:generate', [
        '--schema' => $schemaPath,
        '--criteria' => 'c',
        '--count' => 2,
        '--format' => 'php',
        '--output' => $outputPath,
    ]);

    expect($exit)->toBe(0);

    $loaded = require $outputPath;
    expect($loaded)->toBeArray()->toHaveCount(3)
        ->and($loaded[0]['input'])->toBe('existing-1')
        ->and($loaded[1]['input'])->toBe('new-1')
        ->and($loaded[2]['input'])->toBe('new-2');
});

it('loads seed cases from a file', function (): void {
    $captured = null;
    DatasetGeneratorAgent::fake(function ($prompt, $attachments, $provider, $model) use (&$captured): string {
        $captured = $prompt;

        return json_encode([['input' => 'gen-1']], JSON_UNESCAPED_UNICODE) ?: '';
    });

    $dir = proofread_tmp_gen_dir();
    $schemaPath = $dir.'/s.json';
    file_put_contents($schemaPath, (string) json_encode(['type' => 'string']));
    $seedPath = $dir.'/seed.php';
    file_put_contents($seedPath, "<?php\n\nreturn ".var_export([
        ['input' => 'SEED_FROM_FILE_MARKER'],
    ], true).";\n");

    $exit = Artisan::call('dataset:generate', [
        '--schema' => $schemaPath,
        '--criteria' => 'c',
        '--count' => 1,
        '--seed' => $seedPath,
        '--format' => 'json',
    ]);

    expect($exit)->toBe(0)
        ->and($captured)->toContain('SEED_FROM_FILE_MARKER');
});

it('fails with exit 1 when generator throws', function (): void {
    DatasetGeneratorAgent::fake(['junk', 'still junk']);

    $dir = proofread_tmp_gen_dir();
    $schemaPath = $dir.'/s.json';
    file_put_contents($schemaPath, (string) json_encode(['type' => 'string']));

    $exit = Artisan::call('dataset:generate', [
        '--schema' => $schemaPath,
        '--criteria' => 'c',
        '--count' => 1,
    ]);

    expect($exit)->toBe(1);
});

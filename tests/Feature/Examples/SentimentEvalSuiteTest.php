<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Mosaiqo\Proofread\Examples\ExampleAgent;
use Mosaiqo\Proofread\Examples\SentimentEvalSuite;

beforeEach(function (): void {
    ExampleAgent::fake(fn (string $prompt): string => match (true) {
        str_contains($prompt, 'love') => 'positive',
        str_contains($prompt, 'terrible') => 'negative',
        default => 'neutral',
    });
});

afterEach(function (): void {
    $base = sys_get_temp_dir();
    foreach (glob($base.'/proofread-example-*') ?: [] as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }
});

it('runs end-to-end via the Artisan command with a faked agent', function (): void {
    $exit = Artisan::call('evals:run', [
        'suites' => [SentimentEvalSuite::class],
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain(SentimentEvalSuite::class)
        ->and($output)->toContain('3/3 passed');
});

it('writes JUnit output to the configured path', function (): void {
    $dir = sys_get_temp_dir().'/proofread-example-'.bin2hex(random_bytes(4));
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $path = $dir.'/sentiment.xml';

    $exit = Artisan::call('evals:run', [
        'suites' => [SentimentEvalSuite::class],
        '--junit' => $path,
    ]);

    expect($exit)->toBe(0)
        ->and(file_exists($path))->toBeTrue();

    $xml = file_get_contents($path);
    expect($xml)->toContain('<testsuites')
        ->and($xml)->toContain('name="sentiment-classification"');
});

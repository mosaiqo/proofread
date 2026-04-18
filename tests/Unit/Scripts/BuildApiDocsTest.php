<?php

declare(strict_types=1);

/**
 * @param  list<string>  $args
 * @return array{output: string}
 */
function runBuildApiDocs(array $args = []): array
{
    $script = __DIR__.'/../../../scripts/build-api-docs.php';
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script);
    foreach ($args as $arg) {
        $cmd .= ' '.escapeshellarg($arg);
    }
    $cmd .= ' 2>&1';
    $output = shell_exec($cmd);

    return ['output' => (string) $output];
}

it('runs in dry-run mode and emits a JSON manifest covering all categories', function () {
    $result = runBuildApiDocs(['--dry-run']);
    $output = $result['output'];

    $jsonStart = strpos($output, '{');
    if ($jsonStart === false) {
        throw new RuntimeException('Expected JSON manifest in output, got: '.$output);
    }

    $manifest = json_decode(substr($output, $jsonStart), true);
    expect($manifest)->toBeArray()
        ->and($manifest)->toHaveKeys(['assertions', 'commands', 'runner', 'models', 'support', 'contracts']);

    foreach ($manifest as $category) {
        expect($category)
            ->toHaveKeys(['title', 'path', 'classCount', 'classes', 'wordCount'])
            ->and($category['classCount'])->toBeGreaterThan(0)
            ->and($category['path'])->toStartWith('site/src/content/docs/80-api-reference/');
    }
});

it('lists ContainsAssertion among the assertions category', function () {
    $result = runBuildApiDocs(['--dry-run']);
    $jsonStart = strpos($result['output'], '{');
    if ($jsonStart === false) {
        throw new RuntimeException('Expected JSON manifest in output, got: '.$result['output']);
    }
    $manifest = json_decode(substr($result['output'], $jsonStart), true);

    expect($manifest['assertions']['classes'])
        ->toContain('Mosaiqo\\Proofread\\Assertions\\ContainsAssertion')
        ->toContain('Mosaiqo\\Proofread\\Assertions\\RegexAssertion')
        ->toContain('Mosaiqo\\Proofread\\Assertions\\Rubric');
});

it('writes markdown files with valid frontmatter and method signatures', function () {
    runBuildApiDocs();

    $dir = __DIR__.'/../../../site/src/content/docs/80-api-reference';
    $files = [
        '01-assertions-api.md',
        '02-artisan-commands.md',
        '03-runner.md',
        '04-models.md',
        '05-value-objects.md',
        '06-contracts.md',
    ];

    foreach ($files as $name) {
        $path = $dir.'/'.$name;
        expect(file_exists($path))->toBeTrue("Missing {$name}");
        $content = (string) file_get_contents($path);
        expect($content)->toStartWith('---')
            ->and($content)->toContain('section: "API Reference"')
            ->and($content)->toContain('## Summary');
    }

    $assertions = (string) file_get_contents($dir.'/01-assertions-api.md');
    expect($assertions)->toContain('## `ContainsAssertion`')
        ->and($assertions)->toContain('public static function make(string $needle, bool $caseSensitive = true): self');

    $commands = (string) file_get_contents($dir.'/02-artisan-commands.md');
    expect($commands)->toContain('php artisan evals:run');
});

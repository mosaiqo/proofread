<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function proofread_cleanup_assertion_dir(): void
{
    $dir = app_path('Evals');
    if (! is_dir($dir)) {
        return;
    }

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($rii as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if ($fileInfo->isDir()) {
            @rmdir($fileInfo->getPathname());

            continue;
        }
        @unlink($fileInfo->getPathname());
    }

    @rmdir($dir);
}

beforeEach(function (): void {
    proofread_cleanup_assertion_dir();
});

afterEach(function (): void {
    proofread_cleanup_assertion_dir();
});

it('generates a new Assertion class', function (): void {
    $exit = Artisan::call('proofread:make-assertion', ['name' => 'MyCustomAssertion']);

    $expectedPath = app_path('Evals/Assertions/MyCustomAssertion.php');

    expect($exit)->toBe(0)
        ->and(is_file($expectedPath))->toBeTrue();

    $contents = (string) file_get_contents($expectedPath);

    expect($contents)
        ->toContain('namespace App\\Evals\\Assertions;')
        ->toContain('final readonly class MyCustomAssertion implements Assertion')
        ->toContain('public function run(mixed $output, array $context = []): AssertionResult')
        ->toContain('public function name(): string');
});

it('includes a snake_case name in the generated assertion', function (): void {
    Artisan::call('proofread:make-assertion', ['name' => 'MyCustomAssertion']);

    $expectedPath = app_path('Evals/Assertions/MyCustomAssertion.php');
    $contents = (string) file_get_contents($expectedPath);

    expect($contents)->toContain("return 'my_custom';");
});

it('derives a snake_case name from a suffix-less class', function (): void {
    Artisan::call('proofread:make-assertion', ['name' => 'SentimentChecker']);

    $expectedPath = app_path('Evals/Assertions/SentimentChecker.php');
    $contents = (string) file_get_contents($expectedPath);

    expect($contents)->toContain("return 'sentiment_checker';");
});

it('refuses to overwrite an existing assertion file', function (): void {
    Artisan::call('proofread:make-assertion', ['name' => 'DupeAssertion']);

    Artisan::call('proofread:make-assertion', ['name' => 'DupeAssertion']);

    expect(Artisan::output())->toContain('already exists');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function proofread_cleanup_suite_dir(): void
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
    proofread_cleanup_suite_dir();
});

afterEach(function (): void {
    proofread_cleanup_suite_dir();
});

it('generates a new EvalSuite class', function (): void {
    $exit = Artisan::call('proofread:make-suite', ['name' => 'MyFirstSuite']);

    $expectedPath = app_path('Evals/MyFirstSuite.php');

    expect($exit)->toBe(0)
        ->and(is_file($expectedPath))->toBeTrue();

    $contents = (string) file_get_contents($expectedPath);

    expect($contents)
        ->toContain('namespace App\\Evals;')
        ->toContain('final class MyFirstSuite extends EvalSuite')
        ->toContain("return 'MyFirstSuite';")
        ->toContain('ContainsAssertion::make(');
});

it('generates a MultiSubjectEvalSuite with --multi flag', function (): void {
    $exit = Artisan::call('proofread:make-suite', [
        'name' => 'MultiSuite',
        '--multi' => true,
    ]);

    $expectedPath = app_path('Evals/MultiSuite.php');

    expect($exit)->toBe(0)
        ->and(is_file($expectedPath))->toBeTrue();

    $contents = (string) file_get_contents($expectedPath);

    expect($contents)
        ->toContain('final class MultiSuite extends MultiSubjectEvalSuite')
        ->toContain('public function subjects(): array');

    expect($contents)->not->toContain('public function subject(): mixed');
});

it('respects nested paths in the suite name', function (): void {
    $exit = Artisan::call('proofread:make-suite', ['name' => 'Nested/NestedSuite']);

    $expectedPath = app_path('Evals/Nested/NestedSuite.php');

    expect($exit)->toBe(0)
        ->and(is_file($expectedPath))->toBeTrue();

    $contents = (string) file_get_contents($expectedPath);

    expect($contents)
        ->toContain('namespace App\\Evals\\Nested;')
        ->toContain('final class NestedSuite extends EvalSuite');
});

it('refuses to overwrite an existing suite file', function (): void {
    Artisan::call('proofread:make-suite', ['name' => 'DuplicateSuite']);

    Artisan::call('proofread:make-suite', ['name' => 'DuplicateSuite']);

    expect(Artisan::output())->toContain('already exists');
});

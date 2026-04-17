<?php

declare(strict_types=1);

function proofreadPackageRootPath(string $relative = ''): string
{
    return __DIR__.'/../../'.ltrim($relative, '/');
}

/**
 * @return list<string>
 */
function proofreadCollectFilesUnder(string $directory, string $extensionPattern): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        if (preg_match($extensionPattern, $file->getFilename()) === 1) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

it('does not reference Claude as an assistant in public artifacts', function (): void {
    $paths = [
        proofreadPackageRootPath('composer.json'),
        proofreadPackageRootPath('README.md'),
        proofreadPackageRootPath('CHANGELOG.md'),
        proofreadPackageRootPath('UPGRADING.md'),
        proofreadPackageRootPath('CONTRIBUTING.md'),
    ];

    $forbidden = [
        '/\bClaude assistant\b/',
        '/generated with Claude/i',
        '/Co-Authored-By:\s*Claude/i',
    ];

    foreach ($paths as $path) {
        if (! file_exists($path)) {
            continue;
        }

        $content = file_get_contents($path);

        expect($content)->toBeString();

        foreach ($forbidden as $pattern) {
            expect($content)->not->toMatch($pattern);
        }
    }
});

it('does not reference Claude in stubs or resources', function (): void {
    $directories = [
        proofreadPackageRootPath('stubs'),
        proofreadPackageRootPath('resources'),
    ];

    $forbidden = [
        '/\bClaude assistant\b/',
        '/generated with Claude/i',
        '/Co-Authored-By:\s*Claude/i',
    ];

    foreach ($directories as $directory) {
        $files = proofreadCollectFilesUnder($directory, '/\.(php|md|blade\.php|stub|yml|yaml|json|txt)$/i');

        foreach ($files as $file) {
            $content = file_get_contents($file);

            expect($content)->toBeString();

            foreach ($forbidden as $pattern) {
                expect($content)->not->toMatch($pattern);
            }
        }
    }
});

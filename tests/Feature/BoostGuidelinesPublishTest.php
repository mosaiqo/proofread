<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

function proofreadPublishedBoostGuidelinesPath(): string
{
    return base_path('.ai/guidelines/proofread.md');
}

function proofreadClearPublishedBoostGuidelines(): void
{
    $path = proofreadPublishedBoostGuidelinesPath();

    if (File::exists($path)) {
        File::delete($path);
    }

    $dir = dirname($path);

    if (File::isDirectory($dir) && count(File::files($dir)) === 0) {
        File::deleteDirectory($dir);
    }

    $parent = dirname($dir);

    if (File::isDirectory($parent) && count(File::files($parent)) === 0 && count(File::directories($parent)) === 0) {
        File::deleteDirectory($parent);
    }
}

beforeEach(function (): void {
    proofreadClearPublishedBoostGuidelines();
});

afterEach(function (): void {
    proofreadClearPublishedBoostGuidelines();
});

it('publishes the Boost guidelines stub', function (): void {
    Artisan::call('vendor:publish', [
        '--tag' => 'proofread-boost-guidelines',
        '--force' => true,
    ]);

    expect(File::exists(proofreadPublishedBoostGuidelinesPath()))->toBeTrue(
        'expected .ai/guidelines/proofread.md to exist after publishing the proofread-boost-guidelines tag'
    );
});

it('produces a non-empty markdown file', function (): void {
    Artisan::call('vendor:publish', [
        '--tag' => 'proofread-boost-guidelines',
        '--force' => true,
    ]);

    $contents = File::get(proofreadPublishedBoostGuidelinesPath());

    expect(strlen($contents))->toBeGreaterThan(500);
});

it('does not reference specific AI assistants by name', function (): void {
    Artisan::call('vendor:publish', [
        '--tag' => 'proofread-boost-guidelines',
        '--force' => true,
    ]);

    $contents = File::get(proofreadPublishedBoostGuidelinesPath());

    expect($contents)->not->toContain('Claude')
        ->and($contents)->not->toContain('GPT')
        ->and($contents)->not->toContain('Copilot');
});

it('mentions the core Proofread building blocks', function (): void {
    Artisan::call('vendor:publish', [
        '--tag' => 'proofread-boost-guidelines',
        '--force' => true,
    ]);

    $contents = File::get(proofreadPublishedBoostGuidelinesPath());

    expect($contents)->toContain('EvalSuite')
        ->and($contents)->toContain('Dataset')
        ->and($contents)->toContain('Assertion')
        ->and($contents)->toContain('toPassEval');
});

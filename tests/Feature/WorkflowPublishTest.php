<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

function proofreadPublishedWorkflowPath(): string
{
    return base_path('.github/workflows/proofread.yml');
}

function proofreadClearPublishedWorkflow(): void
{
    $path = proofreadPublishedWorkflowPath();

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
    proofreadClearPublishedWorkflow();
});

afterEach(function (): void {
    proofreadClearPublishedWorkflow();
});

it('publishes the workflow stub to .github/workflows', function (): void {
    Artisan::call('vendor:publish', [
        '--tag' => 'proofread-workflows',
        '--force' => true,
    ]);

    expect(File::exists(proofreadPublishedWorkflowPath()))->toBeTrue(
        'expected .github/workflows/proofread.yml to exist after publishing the proofread-workflows tag'
    );
});

it('produces a valid YAML file', function (): void {
    Artisan::call('vendor:publish', [
        '--tag' => 'proofread-workflows',
        '--force' => true,
    ]);

    $contents = File::get(proofreadPublishedWorkflowPath());
    $parsed = Yaml::parse($contents);

    expect($parsed)->toBeArray()
        ->and($parsed)->toHaveKeys(['name', 'on', 'jobs']);
});

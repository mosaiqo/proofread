<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

function tempJUnitDir(): string
{
    $base = sys_get_temp_dir().'/proofread-junit-'.bin2hex(random_bytes(6));
    if (! mkdir($base, 0755, true) && ! is_dir($base)) {
        throw new RuntimeException('Failed to create temp dir: '.$base);
    }

    return $base;
}

function removeDir(string $path): void
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
            removeDir($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($path);
}

function sampleRun(): EvalRun
{
    $dataset = Dataset::make('d', [['input' => 'a']]);

    return EvalRun::make(
        $dataset,
        [EvalResult::make(['input' => 'a'], 'a', [AssertionResult::pass()], 1.0)],
        1.0,
    );
}

it('writes the JUnit XML to the given path', function (): void {
    $dir = tempJUnitDir();
    $path = $dir.'/report.xml';

    try {
        Proofread::writeJUnit(sampleRun(), $path);

        expect(file_exists($path))->toBeTrue();
        $contents = file_get_contents($path);
        expect($contents)->toBeString();
        expect($contents)->toContain('<testsuites');
    } finally {
        removeDir($dir);
    }
});

it('creates parent directories that do not exist', function (): void {
    $dir = tempJUnitDir();
    $path = $dir.'/nested/deep/report.xml';

    try {
        Proofread::writeJUnit(sampleRun(), $path);

        expect(is_dir($dir.'/nested/deep'))->toBeTrue();
        expect(file_exists($path))->toBeTrue();
    } finally {
        removeDir($dir);
    }
});

it('overwrites an existing file atomically', function (): void {
    $dir = tempJUnitDir();
    $path = $dir.'/report.xml';
    file_put_contents($path, 'stale');

    try {
        Proofread::writeJUnit(sampleRun(), $path);

        $contents = file_get_contents($path);
        expect($contents)->toBeString();
        expect($contents)->not->toBe('stale');
        expect($contents)->toContain('<testsuites');
    } finally {
        removeDir($dir);
    }
});

it('produces valid XML at the destination', function (): void {
    $dir = tempJUnitDir();
    $path = $dir.'/report.xml';
    $run = sampleRun();

    try {
        Proofread::writeJUnit($run, $path);

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $doc->load($path);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        expect($loaded)->toBeTrue();

        $fromFile = file_get_contents($path);
        expect($fromFile)->toBe($run->toJUnitXml());
    } finally {
        removeDir($dir);
    }
});

it('throws when the destination directory cannot be created', function (): void {
    $dir = tempJUnitDir();
    $readOnly = $dir.'/readonly';
    mkdir($readOnly, 0555);

    try {
        Proofread::writeJUnit(sampleRun(), $readOnly.'/sub/report.xml');
    } finally {
        chmod($readOnly, 0755);
        removeDir($dir);
    }
})->throws(RuntimeException::class);

it('leaves no tmp file behind on success', function (): void {
    $dir = tempJUnitDir();
    $path = $dir.'/report.xml';

    try {
        Proofread::writeJUnit(sampleRun(), $path);

        $entries = array_values(array_filter(
            scandir($dir) ?: [],
            fn (string $e): bool => $e !== '.' && $e !== '..',
        ));
        expect($entries)->toBe(['report.xml']);
    } finally {
        removeDir($dir);
    }
});

it('can be invoked via the EvalRun::saveJUnitTo sugar', function (): void {
    $dir = tempJUnitDir();
    $path = $dir.'/report.xml';
    $run = sampleRun();

    try {
        $run->saveJUnitTo($path);

        expect(file_exists($path))->toBeTrue();
        $contents = file_get_contents($path);
        expect($contents)->toBe($run->toJUnitXml());
    } finally {
        removeDir($dir);
    }
});

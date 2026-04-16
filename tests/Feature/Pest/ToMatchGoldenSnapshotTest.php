<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Proofread;
use Mosaiqo\Proofread\Snapshot\SnapshotStore;
use PHPUnit\Framework\ExpectationFailedException;

beforeEach(function (): void {
    Proofread::registerPestExpectations();

    $this->snapshotDir = sys_get_temp_dir().'/proofread-golden-pest-'.bin2hex(random_bytes(6));
    if (! mkdir($this->snapshotDir, 0755, true) && ! is_dir($this->snapshotDir)) {
        throw new RuntimeException('Failed to create temp dir: '.$this->snapshotDir);
    }

    $this->store = new SnapshotStore($this->snapshotDir);
    $this->app->instance(SnapshotStore::class, $this->store);
});

afterEach(function (): void {
    if (isset($this->snapshotDir) && is_string($this->snapshotDir)) {
        removeGoldenPestDir($this->snapshotDir);
    }
});

function removeGoldenPestDir(string $path): void
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
            removeGoldenPestDir($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($path);
}

it('creates a snapshot on first run with an explicit key', function (): void {
    expect('hello world')->toMatchGoldenSnapshot('pest/explicit');

    expect($this->store->has('pest/explicit'))->toBeTrue()
        ->and($this->store->get('pest/explicit'))->toBe("hello world\n");
});

it('passes when output matches the snapshot', function (): void {
    $this->store->put('pest/match', 'matching');

    expect('matching')->toMatchGoldenSnapshot('pest/match');
});

it('fails with a diff when output differs', function (): void {
    $this->store->put('pest/diff', "line one\nline two");

    $caught = null;
    try {
        expect("line one\nline TWO")->toMatchGoldenSnapshot('pest/diff');
    } catch (ExpectationFailedException $exception) {
        $caught = $exception;
    }

    if (! $caught instanceof ExpectationFailedException) {
        throw new RuntimeException('Expected ExpectationFailedException was not thrown.');
    }

    expect($caught->getMessage())->toContain('does not match')
        ->and($caught->getMessage())->toContain('-')
        ->and($caught->getMessage())->toContain('+');
});

it('supports negation', function (): void {
    $this->store->put('pest/neg', 'stored');

    expect('different')->not->toMatchGoldenSnapshot('pest/neg');
});

it('derives a key automatically from test context', function (): void {
    expect('auto-derived content')->toMatchGoldenSnapshot();

    $keys = scanSnapshotKeys($this->snapshotDir);
    expect($keys)->not->toBeEmpty();
});

it('produces unique keys for different tests in the same file', function (): void {
    expect('content for first unique test')->toMatchGoldenSnapshot();

    $keysFirst = scanSnapshotKeys($this->snapshotDir);
    expect($keysFirst)->toHaveCount(1);
});

it('produces a different key than the previous unique test', function (): void {
    expect('content for second unique test')->toMatchGoldenSnapshot();

    $keys = scanSnapshotKeys($this->snapshotDir);
    expect($keys)->toHaveCount(1);
});

/**
 * @return array<int, string>
 */
function scanSnapshotKeys(string $dir): array
{
    $keys = [];
    if (! is_dir($dir)) {
        return $keys;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.snap')) {
            $keys[] = $file->getPathname();
        }
    }

    return $keys;
}

<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\GoldenSnapshot;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Snapshot\SnapshotStore;
use Mosaiqo\Proofread\Support\AssertionResult;

beforeEach(function (): void {
    $this->snapshotDir = sys_get_temp_dir().'/proofread-golden-'.bin2hex(random_bytes(6));
    if (! mkdir($this->snapshotDir, 0755, true) && ! is_dir($this->snapshotDir)) {
        throw new RuntimeException('Failed to create temp dir: '.$this->snapshotDir);
    }

    $this->store = new SnapshotStore($this->snapshotDir);
    $this->app->instance(SnapshotStore::class, $this->store);
});

afterEach(function (): void {
    if (isset($this->snapshotDir) && is_string($this->snapshotDir)) {
        removeGoldenDir($this->snapshotDir);
    }
});

function removeGoldenDir(string $path): void
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
            removeGoldenDir($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($path);
}

it('creates a snapshot on first run', function (): void {
    $result = GoldenSnapshot::forKey('first/case')->run('hello world');

    expect($result)->toBeInstanceOf(AssertionResult::class)
        ->and($result->passed)->toBeTrue()
        ->and($result->reason)->toContain('created')
        ->and($result->metadata)->toHaveKey('snapshot_created')
        ->and($result->metadata['snapshot_created'])->toBeTrue()
        ->and($this->store->has('first/case'))->toBeTrue()
        ->and($this->store->get('first/case'))->toBe("hello world\n");
});

it('passes when output matches the existing snapshot', function (): void {
    $this->store->put('match/case', 'expected output');

    $result = GoldenSnapshot::forKey('match/case')->run('expected output');

    expect($result->passed)->toBeTrue()
        ->and($result->reason)->toContain('matches');
});

it('fails when output differs from the snapshot', function (): void {
    $this->store->put('diff/case', 'expected output');

    $result = GoldenSnapshot::forKey('diff/case')->run('actual output');

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('does not match');
});

it('includes a diff in the failure reason', function (): void {
    $this->store->put('diff/reason', "line one\nline two\nline three");

    $result = GoldenSnapshot::forKey('diff/reason')->run("line one\nline TWO\nline three");

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('-')
        ->and($result->reason)->toContain('+')
        ->and($result->reason)->toContain('line two')
        ->and($result->reason)->toContain('line TWO');
    expect($result->metadata)->toHaveKey('snapshot_diff');
});

it('truncates long diffs', function (): void {
    $expectedLines = [];
    $actualLines = [];
    for ($i = 0; $i < 50; $i++) {
        $expectedLines[] = 'expected '.$i;
        $actualLines[] = 'actual '.$i;
    }
    $this->store->put('diff/truncate', implode("\n", $expectedLines));

    $result = GoldenSnapshot::forKey('diff/truncate')->run(implode("\n", $actualLines));

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('truncated');
});

it('updates the snapshot when updateMode is enabled', function (): void {
    $store = new SnapshotStore($this->snapshotDir, updateMode: true);
    $this->app->instance(SnapshotStore::class, $store);
    $store->put('update/case', 'stale');

    $result = GoldenSnapshot::forKey('update/case')->run('fresh');

    expect($result->passed)->toBeTrue()
        ->and($result->reason)->toContain('updated')
        ->and($result->metadata)->toHaveKey('snapshot_updated')
        ->and($result->metadata['snapshot_updated'])->toBeTrue()
        ->and($store->get('update/case'))->toBe("fresh\n");
});

it('serializes non-string output as JSON', function (): void {
    $result = GoldenSnapshot::forKey('json/case')->run(['foo' => 'bar', 'baz' => [1, 2, 3]]);

    expect($result->passed)->toBeTrue();
    $stored = $this->store->get('json/case');
    expect($stored)->toContain('"foo": "bar"');
    expect($stored)->toContain('"baz"');
});

it('fails when output cannot be serialized', function (): void {
    $resource = fopen('php://memory', 'r');
    try {
        $result = GoldenSnapshot::forKey('unserializable/case')->run($resource);

        expect($result->passed)->toBeFalse()
            ->and($result->reason)->toContain('requires string or JSON-serializable output');
    } finally {
        if (is_resource($resource)) {
            fclose($resource);
        }
    }
});

it('derives key from meta.snapshot_key when fromContext', function (): void {
    $result = GoldenSnapshot::fromContext()->run('output', [
        'meta' => ['snapshot_key' => 'derived/key'],
    ]);

    expect($result->passed)->toBeTrue()
        ->and($result->metadata)->toHaveKey('snapshot_key')
        ->and($result->metadata['snapshot_key'])->toBe('derived/key');
    expect($this->store->has('derived/key'))->toBeTrue();
});

it('derives key from meta.name when snapshot_key absent', function (): void {
    $result = GoldenSnapshot::fromContext()->run('output', [
        'meta' => ['name' => 'my-case'],
    ]);

    expect($result->passed)->toBeTrue()
        ->and($result->metadata['snapshot_key'])->toBe('my-case');
});

it('derives key from case_index when both meta keys absent', function (): void {
    $result = GoldenSnapshot::fromContext()->run('output', [
        'case_index' => 3,
    ]);

    expect($result->passed)->toBeTrue()
        ->and($result->metadata['snapshot_key'])->toBe('case_3');
});

it('fails when no key source is available in fromContext', function (): void {
    $result = GoldenSnapshot::fromContext()->run('output', []);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('meta.name')
        ->and($result->reason)->toContain('case_index');
});

it('exposes the snapshot key and path in metadata', function (): void {
    $result = GoldenSnapshot::forKey('meta/case')->run('value');

    expect($result->metadata)->toHaveKey('snapshot_key');
    expect($result->metadata)->toHaveKey('snapshot_path');
    expect($result->metadata['snapshot_key'])->toBe('meta/case');
    expect($result->metadata['snapshot_path'])->toBe($this->store->path('meta/case'));
});

it('exposes name as "golden_snapshot"', function (): void {
    expect(GoldenSnapshot::forKey('any')->name())->toBe('golden_snapshot');
});

it('implements the Assertion contract', function (): void {
    expect(GoldenSnapshot::forKey('any'))->toBeInstanceOf(Assertion::class);
});

it('rejects empty key in forKey', function (): void {
    GoldenSnapshot::forKey('');
})->throws(InvalidArgumentException::class);

it('normalizes trailing newlines during comparison', function (): void {
    $this->store->put('newline/case', "foo\n");

    $result = GoldenSnapshot::forKey('newline/case')->run('foo');

    expect($result->passed)->toBeTrue()
        ->and($result->reason)->toContain('matches');
});

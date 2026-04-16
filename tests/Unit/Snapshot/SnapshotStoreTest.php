<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Snapshot\SnapshotException;
use Mosaiqo\Proofread\Snapshot\SnapshotStore;

function tempSnapshotDir(): string
{
    $base = sys_get_temp_dir().'/proofread-snapshots-'.bin2hex(random_bytes(6));
    if (! mkdir($base, 0755, true) && ! is_dir($base)) {
        throw new RuntimeException('Failed to create temp dir: '.$base);
    }

    return $base;
}

function removeSnapshotDir(string $path): void
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
            removeSnapshotDir($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($path);
}

it('returns the snapshot path for a key', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);

        expect($store->path('foo'))->toBe($dir.'/foo.snap');
        expect($store->path('nested/bar'))->toBe($dir.'/nested/bar.snap');
    } finally {
        removeSnapshotDir($dir);
    }
});

it('sanitizes unsafe characters in keys', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);

        $path = $store->path('foo<>bar baz!');

        expect($path)->toBe($dir.'/foo__bar_baz_.snap');
        expect($path)->not->toContain('<');
        expect($path)->not->toContain('>');
        expect($path)->not->toContain(' ');
    } finally {
        removeSnapshotDir($dir);
    }
});

it('normalizes backslash separators to forward slashes', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);

        $path = $store->path('nested\\foo\\bar');

        expect($path)->toBe($dir.'/nested/foo/bar.snap');
    } finally {
        removeSnapshotDir($dir);
    }
});

it('rejects empty keys', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->path('');
    } finally {
        removeSnapshotDir($dir);
    }
})->throws(SnapshotException::class);

it('rejects keys that attempt path traversal', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->path('../outside');
    } finally {
        removeSnapshotDir($dir);
    }
})->throws(SnapshotException::class);

it('rejects keys that contain ".." segments after sanitization', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->path('foo/../bar');
    } finally {
        removeSnapshotDir($dir);
    }
})->throws(SnapshotException::class);

it('has() returns false when snapshot does not exist', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);

        expect($store->has('missing'))->toBeFalse();
    } finally {
        removeSnapshotDir($dir);
    }
});

it('has() returns true after put()', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->put('exists', 'value');

        expect($store->has('exists'))->toBeTrue();
    } finally {
        removeSnapshotDir($dir);
    }
});

it('put() creates the parent directory when missing', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->put('deeply/nested/key', 'value');

        expect(is_dir($dir.'/deeply/nested'))->toBeTrue();
        expect(file_exists($dir.'/deeply/nested/key.snap'))->toBeTrue();
    } finally {
        removeSnapshotDir($dir);
    }
});

it('put() writes atomically and leaves no tmp file', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->put('key', 'value');

        $entries = array_values(array_filter(
            scandir($dir) ?: [],
            fn (string $e): bool => $e !== '.' && $e !== '..',
        ));

        expect($entries)->toBe(['key.snap']);
    } finally {
        removeSnapshotDir($dir);
    }
});

it('put() appends a trailing newline if missing', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->put('key', 'hello');

        $contents = file_get_contents($dir.'/key.snap');
        expect($contents)->toBe("hello\n");
    } finally {
        removeSnapshotDir($dir);
    }
});

it('put() does not double up an existing trailing newline', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->put('key', "hello\n");

        $contents = file_get_contents($dir.'/key.snap');
        expect($contents)->toBe("hello\n");
    } finally {
        removeSnapshotDir($dir);
    }
});

it('get() returns the stored content', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->put('key', "hello\nworld");

        expect($store->get('key'))->toBe("hello\nworld\n");
    } finally {
        removeSnapshotDir($dir);
    }
});

it('get() throws when the snapshot does not exist', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->get('missing');
    } finally {
        removeSnapshotDir($dir);
    }
})->throws(SnapshotException::class);

it('delete() removes an existing snapshot', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->put('key', 'value');

        expect($store->delete('key'))->toBeTrue();
        expect($store->has('key'))->toBeFalse();
    } finally {
        removeSnapshotDir($dir);
    }
});

it('delete() returns false for a missing snapshot', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);

        expect($store->delete('missing'))->toBeFalse();
    } finally {
        removeSnapshotDir($dir);
    }
});

it('overwrites on put when the key exists', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir);
        $store->put('key', 'first');
        $store->put('key', 'second');

        expect($store->get('key'))->toBe("second\n");
    } finally {
        removeSnapshotDir($dir);
    }
});

it('exposes the base path and update mode', function (): void {
    $dir = tempSnapshotDir();
    try {
        $store = new SnapshotStore($dir, updateMode: true);

        expect($store->basePath)->toBe($dir);
        expect($store->updateMode)->toBeTrue();
    } finally {
        removeSnapshotDir($dir);
    }
});

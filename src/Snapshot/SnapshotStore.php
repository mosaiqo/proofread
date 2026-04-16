<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Snapshot;

final class SnapshotStore
{
    public function __construct(
        public readonly string $basePath,
        public readonly bool $updateMode = false,
    ) {}

    public function path(string $key): string
    {
        $sanitized = $this->sanitizeKey($key);

        return $this->basePath.'/'.$sanitized.'.snap';
    }

    public function has(string $key): bool
    {
        return file_exists($this->path($key));
    }

    public function get(string $key): string
    {
        $path = $this->path($key);

        if (! file_exists($path)) {
            throw SnapshotException::notFound($key);
        }

        $contents = self::silently(static fn (): string|false => file_get_contents($path));

        if ($contents === false) {
            throw SnapshotException::notFound($key);
        }

        return $contents;
    }

    public function put(string $key, string $value): void
    {
        $path = $this->path($key);
        $directory = dirname($path);

        if (! is_dir($directory)) {
            $created = self::silently(static fn (): bool => mkdir($directory, 0755, true));
            if (! $created && ! is_dir($directory)) {
                throw SnapshotException::writeFailed($path);
            }
        }

        if ($value === '' || $value[strlen($value) - 1] !== "\n") {
            $value .= "\n";
        }

        $tmpPath = $path.'.'.getmypid().'.tmp';

        $bytes = self::silently(static fn (): int|false => file_put_contents($tmpPath, $value));
        if ($bytes === false) {
            throw SnapshotException::writeFailed($path);
        }

        $renamed = self::silently(static fn (): bool => rename($tmpPath, $path));
        if (! $renamed) {
            self::silently(static fn (): bool => unlink($tmpPath));
            throw SnapshotException::writeFailed($path);
        }
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);

        if (! file_exists($path)) {
            return false;
        }

        return (bool) self::silently(static fn (): bool => unlink($path));
    }

    private function sanitizeKey(string $key): string
    {
        if ($key === '') {
            throw SnapshotException::emptyKey();
        }

        $normalized = str_replace('\\', '/', $key);
        $sanitized = (string) preg_replace('#[^A-Za-z0-9._\-/]#', '_', $normalized);

        $segments = explode('/', $sanitized);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                throw SnapshotException::pathTraversal($key);
            }
        }

        $trimmed = trim($sanitized, '/');

        if ($trimmed === '') {
            throw SnapshotException::emptyKey();
        }

        return $trimmed;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private static function silently(callable $fn): mixed
    {
        set_error_handler(static fn (): bool => true);
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }
}

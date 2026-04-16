<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Snapshot;

use RuntimeException;

class SnapshotException extends RuntimeException
{
    public static function emptyKey(): self
    {
        return new self('Snapshot key must not be empty.');
    }

    public static function pathTraversal(string $key): self
    {
        return new self(sprintf('Snapshot key "%s" is not allowed: path traversal segments detected.', $key));
    }

    public static function notFound(string $key): self
    {
        return new self(sprintf('Snapshot "%s" does not exist.', $key));
    }

    public static function writeFailed(string $path): self
    {
        return new self(sprintf('Failed to write snapshot file "%s".', $path));
    }
}

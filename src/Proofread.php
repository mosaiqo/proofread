<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread;

use Mosaiqo\Proofread\Support\EvalRun;
use RuntimeException;

class Proofread
{
    private static bool $pestExpectationsRegistered = false;

    public function version(): string
    {
        return '0.1.0-dev';
    }

    public static function registerPestExpectations(): void
    {
        if (self::$pestExpectationsRegistered) {
            return;
        }

        require_once __DIR__.'/Testing/expectations.php';

        self::$pestExpectationsRegistered = true;
    }

    public static function writeJUnit(EvalRun $run, string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            self::ensureDirectory($directory);
        }

        if (! is_writable($directory)) {
            throw new RuntimeException(
                sprintf('JUnit output directory "%s" is not writable.', $directory)
            );
        }

        $xml = $run->toJUnitXml();
        $tmpPath = $path.'.'.getmypid().'.tmp';

        $bytes = self::silently(static fn (): int|false => file_put_contents($tmpPath, $xml));
        if ($bytes === false) {
            throw new RuntimeException(
                sprintf('Unable to write JUnit XML to temporary file "%s".', $tmpPath)
            );
        }

        $renamed = self::silently(static fn (): bool => rename($tmpPath, $path));
        if (! $renamed) {
            self::silently(static fn (): bool => unlink($tmpPath));
            throw new RuntimeException(
                sprintf('Unable to move JUnit XML to "%s".', $path)
            );
        }
    }

    private static function ensureDirectory(string $directory): void
    {
        $created = self::silently(static fn (): bool => mkdir($directory, 0755, true));
        if (! $created && ! is_dir($directory)) {
            throw new RuntimeException(
                sprintf('Unable to create JUnit output directory "%s".', $directory)
            );
        }
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

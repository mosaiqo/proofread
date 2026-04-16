<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Snapshot\SnapshotStore;
use Mosaiqo\Proofread\Support\AssertionResult;
use Throwable;

final class GoldenSnapshot implements Assertion
{
    private const DIFF_MAX_LINES = 30;

    private const DIFF_HEAD_LINES = 15;

    private const DIFF_LINE_MAX_CHARS = 120;

    private function __construct(
        private readonly ?string $explicitKey,
        private readonly bool $deriveFromContext,
    ) {
        if ($explicitKey !== null && $explicitKey === '') {
            throw new InvalidArgumentException('GoldenSnapshot key must not be empty.');
        }
    }

    public static function forKey(string $key): self
    {
        if ($key === '') {
            throw new InvalidArgumentException('GoldenSnapshot key must not be empty.');
        }

        return new self($key, false);
    }

    public static function fromContext(): self
    {
        return new self(null, true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function run(mixed $output, array $context = []): AssertionResult
    {
        $keyResult = $this->resolveKey($context);
        if (is_string($keyResult) === false) {
            return $keyResult;
        }
        $key = $keyResult;

        $serialized = $this->serialize($output);
        if ($serialized instanceof AssertionResult) {
            return $serialized;
        }

        $store = Container::getInstance()->make(SnapshotStore::class);

        $metadataBase = [
            'snapshot_key' => $key,
            'snapshot_path' => $store->path($key),
        ];

        if (! $store->has($key)) {
            $store->put($key, $serialized);

            return AssertionResult::pass(
                sprintf("Snapshot '%s' created", $key),
                null,
                $metadataBase + ['snapshot_created' => true],
            );
        }

        $expected = $store->get($key);

        $normalizedExpected = rtrim($expected, "\n");
        $normalizedActual = rtrim($serialized, "\n");

        if ($normalizedExpected === $normalizedActual) {
            return AssertionResult::pass(
                sprintf("Snapshot '%s' matches", $key),
                null,
                $metadataBase,
            );
        }

        if ($store->updateMode) {
            $store->put($key, $serialized);

            return AssertionResult::pass(
                sprintf("Snapshot '%s' updated", $key),
                null,
                $metadataBase + ['snapshot_updated' => true],
            );
        }

        $diff = self::diff($normalizedExpected, $normalizedActual);

        return AssertionResult::fail(
            sprintf("Snapshot '%s' does not match:\n%s", $key, $diff),
            null,
            $metadataBase + ['snapshot_diff' => $diff],
        );
    }

    public function name(): string
    {
        return 'golden_snapshot';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return string|AssertionResult Returns the resolved key or a failure result if not derivable.
     */
    private function resolveKey(array $context): string|AssertionResult
    {
        if ($this->explicitKey !== null) {
            return $this->explicitKey;
        }

        if ($this->deriveFromContext) {
            $meta = $context['meta'] ?? null;
            if (is_array($meta)) {
                $snapshotKey = $meta['snapshot_key'] ?? null;
                if (is_string($snapshotKey) && $snapshotKey !== '') {
                    return $snapshotKey;
                }

                $name = $meta['name'] ?? null;
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            }

            $caseIndex = $context['case_index'] ?? null;
            if (is_int($caseIndex) || (is_string($caseIndex) && $caseIndex !== '')) {
                return sprintf('case_%s', $caseIndex);
            }

            return AssertionResult::fail(
                'GoldenSnapshot::fromContext() requires meta.name or case_index in context',
            );
        }

        return AssertionResult::fail(
            'GoldenSnapshot requires a key; use forKey() or fromContext()',
        );
    }

    private function serialize(mixed $output): string|AssertionResult
    {
        if (is_string($output)) {
            return $output;
        }

        try {
            $encoded = json_encode(
                $output,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (Throwable) {
            return AssertionResult::fail(
                'GoldenSnapshot requires string or JSON-serializable output',
            );
        }

        return $encoded;
    }

    private static function diff(string $expected, string $actual): string
    {
        $expectedLines = explode("\n", $expected);
        $actualLines = explode("\n", $actual);

        $max = max(count($expectedLines), count($actualLines));
        $lines = [];

        for ($i = 0; $i < $max; $i++) {
            $exp = $expectedLines[$i] ?? null;
            $act = $actualLines[$i] ?? null;

            if ($exp === $act) {
                if ($exp !== null) {
                    $lines[] = '  '.self::truncateLine($exp);
                }

                continue;
            }

            if ($exp !== null) {
                $lines[] = '- '.self::truncateLine($exp);
            }
            if ($act !== null) {
                $lines[] = '+ '.self::truncateLine($act);
            }
        }

        if (count($lines) > self::DIFF_MAX_LINES) {
            $head = array_slice($lines, 0, self::DIFF_HEAD_LINES);
            $remaining = count($lines) - self::DIFF_HEAD_LINES;
            $head[] = sprintf('... (truncated, %d more lines)', $remaining);

            return implode("\n", $head);
        }

        return implode("\n", $lines);
    }

    private static function truncateLine(string $line): string
    {
        if (mb_strlen($line) <= self::DIFF_LINE_MAX_CHARS) {
            return $line;
        }

        return mb_substr($line, 0, self::DIFF_LINE_MAX_CHARS - 3).'...';
    }
}

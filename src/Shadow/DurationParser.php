<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Parse human-readable duration shorthand (e.g. "1h", "30m", "7d", "1w") into
 * seconds or a subtracted DateTimeImmutable anchor. Shared helper so both
 * shadow:evaluate and shadow:alert agree on the same vocabulary.
 */
final class DurationParser
{
    public static function toSeconds(string $duration): int
    {
        $normalized = self::normalize($duration);
        $timestamp = strtotime('-'.$normalized, 0);

        if ($timestamp === false || $timestamp >= 0) {
            throw new InvalidArgumentException(sprintf(
                'Unable to parse duration value "%s". Examples: 1h, 30m, 24h, 7d, 1w.',
                $duration,
            ));
        }

        return -$timestamp;
    }

    public static function ago(string $duration, ?DateTimeInterface $from = null): DateTimeImmutable
    {
        $seconds = self::toSeconds($duration);
        $anchor = $from !== null
            ? DateTimeImmutable::createFromInterface($from)
            : new DateTimeImmutable('now');

        return $anchor->modify('-'.$seconds.' seconds');
    }

    private static function normalize(string $duration): string
    {
        $units = [
            's' => 'seconds',
            'm' => 'minutes',
            'h' => 'hours',
            'd' => 'days',
            'w' => 'weeks',
        ];

        if (preg_match('/^(\d+)\s*([smhdw])$/i', trim($duration), $matches) !== 1) {
            return $duration;
        }

        $amount = $matches[1];
        $unit = strtolower($matches[2]);

        return $amount.' '.$units[$unit];
    }
}

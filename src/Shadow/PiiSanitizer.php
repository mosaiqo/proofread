<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

use Closure;
use InvalidArgumentException;

/**
 * Sanitizes shadow capture payloads before persistence: strips values at
 * known-PII keys, applies redaction patterns, truncates long strings, and
 * neutralizes unserializable values (resources, closures).
 *
 * The class is designed to be called on untrusted user input, so it prefers
 * replacing offending values over raising exceptions.
 */
final readonly class PiiSanitizer
{
    private const int MAX_RECURSION_DEPTH = 16;

    /**
     * @param  list<string>  $piiKeys
     * @param  array<string, string>  $redactPatterns
     */
    public function __construct(
        public array $piiKeys = [],
        public array $redactPatterns = [],
        public int $maxInputLength = 2000,
        public int $maxOutputLength = 5000,
        public string $redactedPlaceholder = '[REDACTED]',
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $piiKeys = $config['pii_keys'] ?? [];
        $redactPatterns = $config['redact_patterns'] ?? [];
        $maxInputLength = $config['max_input_length'] ?? 2000;
        $maxOutputLength = $config['max_output_length'] ?? 5000;
        $redactedPlaceholder = $config['redacted_placeholder'] ?? '[REDACTED]';

        if (! is_array($piiKeys)) {
            throw new InvalidArgumentException('pii_keys must be an array of strings.');
        }
        if (! is_array($redactPatterns)) {
            throw new InvalidArgumentException('redact_patterns must be an array keyed by regex.');
        }
        if (! is_int($maxInputLength)) {
            throw new InvalidArgumentException('max_input_length must be an integer.');
        }
        if (! is_int($maxOutputLength)) {
            throw new InvalidArgumentException('max_output_length must be an integer.');
        }
        if (! is_string($redactedPlaceholder)) {
            throw new InvalidArgumentException('redacted_placeholder must be a string.');
        }

        /** @var list<string> $normalizedPiiKeys */
        $normalizedPiiKeys = array_values(array_map(
            static function (mixed $key): string {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('pii_keys entries must be strings.');
                }

                return $key;
            },
            $piiKeys,
        ));

        /** @var array<string, string> $normalizedPatterns */
        $normalizedPatterns = [];
        foreach ($redactPatterns as $pattern => $replacement) {
            if (! is_string($pattern) || ! is_string($replacement)) {
                throw new InvalidArgumentException('redact_patterns must map string regex to string replacement.');
            }
            $normalizedPatterns[$pattern] = $replacement;
        }

        return new self(
            piiKeys: $normalizedPiiKeys,
            redactPatterns: $normalizedPatterns,
            maxInputLength: $maxInputLength,
            maxOutputLength: $maxOutputLength,
            redactedPlaceholder: $redactedPlaceholder,
        );
    }

    public function sanitizeInput(mixed $input): mixed
    {
        return $this->sanitize($input, 0);
    }

    public function sanitizeOutput(string $output): string
    {
        $redacted = $this->applyPatterns($output);

        return $this->truncate($redacted, $this->maxOutputLength);
    }

    private function sanitize(mixed $value, int $depth): mixed
    {
        if ($depth >= self::MAX_RECURSION_DEPTH) {
            return $this->redactedPlaceholder;
        }

        if (is_string($value)) {
            $redacted = $this->applyPatterns($value);

            return $this->truncate($redacted, $this->maxInputLength);
        }

        if (is_array($value)) {
            return $this->sanitizeArray($value, $depth);
        }

        if (is_object($value)) {
            if ($value instanceof Closure) {
                return $this->redactedPlaceholder;
            }

            /** @var array<array-key, mixed> $asArray */
            $asArray = (array) $value;

            return $this->sanitizeArray($asArray, $depth);
        }

        if (is_resource($value)) {
            return $this->redactedPlaceholder;
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sanitizeArray(array $value, int $depth): array
    {
        $result = [];
        $piiKeysLower = array_map('strtolower', $this->piiKeys);

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), $piiKeysLower, true)) {
                $result[$key] = $this->redactedPlaceholder;

                continue;
            }

            $result[$key] = $this->sanitize($item, $depth + 1);
        }

        return $result;
    }

    private function applyPatterns(string $value): string
    {
        foreach ($this->redactPatterns as $pattern => $replacement) {
            $replaced = preg_replace($pattern, $replacement, $value);
            if (is_string($replaced)) {
                $value = $replaced;
            }
        }

        return $value;
    }

    private function truncate(string $value, int $max): string
    {
        if ($max <= 0 || strlen($value) <= $max) {
            return $value;
        }

        $omitted = strlen($value) - $max;

        return substr($value, 0, $max)."... [truncated, {$omitted} chars omitted]";
    }
}

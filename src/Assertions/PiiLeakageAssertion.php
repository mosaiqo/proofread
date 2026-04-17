<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Assertions;

use Illuminate\Container\Container;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Shadow\PiiSanitizer;
use Mosaiqo\Proofread\Support\AssertionResult;

/**
 * Deterministic PII leakage check. Applies the configured {@see PiiSanitizer}
 * redaction patterns to the output and fails when at least one placeholder
 * has been inserted.
 *
 * Only string outputs are supported because {@see PiiSanitizer::sanitizeOutput}
 * operates on strings. Array/object traversal via PII keys is a concern of
 * shadow input sanitization, not output leakage detection.
 */
final readonly class PiiLeakageAssertion implements Assertion
{
    private function __construct(
        private ?PiiSanitizer $sanitizer,
    ) {}

    public static function make(?PiiSanitizer $sanitizer = null): self
    {
        return new self($sanitizer);
    }

    /**
     * @param  array<string, string>  $redactPatterns
     */
    public static function withPatterns(array $redactPatterns): self
    {
        return new self(new PiiSanitizer(redactPatterns: $redactPatterns));
    }

    public function run(mixed $output, array $context = []): AssertionResult
    {
        if (! is_string($output)) {
            return AssertionResult::fail(
                sprintf('PiiLeakageAssertion requires string output, got %s', gettype($output))
            );
        }

        $sanitizer = $this->sanitizer ?? Container::getInstance()->make(PiiSanitizer::class);
        $sanitized = $sanitizer->sanitizeOutput($output);

        if ($sanitized === $output) {
            return AssertionResult::pass('No PII patterns detected');
        }

        $placeholders = $this->detectPlaceholders($sanitizer, $sanitized);

        return AssertionResult::fail(
            sprintf('PII detected in output: %s', implode(', ', $placeholders)),
            metadata: [
                'placeholders_found' => $placeholders,
                'sanitized_output' => $sanitized,
            ],
        );
    }

    public function name(): string
    {
        return 'pii_leakage';
    }

    /**
     * @return list<string>
     */
    private function detectPlaceholders(PiiSanitizer $sanitizer, string $sanitized): array
    {
        $found = [];

        foreach ($sanitizer->redactPatterns as $replacement) {
            if (str_contains($sanitized, $replacement) && ! in_array($replacement, $found, true)) {
                $found[] = $replacement;
            }
        }

        return $found;
    }
}

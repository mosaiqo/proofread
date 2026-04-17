<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint;

use InvalidArgumentException;

final readonly class LintIssue
{
    private const ALLOWED_SEVERITIES = ['error', 'warning', 'info'];

    public function __construct(
        public string $ruleName,
        public string $severity,
        public string $message,
        public ?string $suggestion = null,
        public ?int $lineNumber = null,
    ) {
        if (! in_array($severity, self::ALLOWED_SEVERITIES, true)) {
            throw new InvalidArgumentException(sprintf(
                "LintIssue severity must be one of [%s], got '%s'.",
                implode(', ', self::ALLOWED_SEVERITIES),
                $severity,
            ));
        }
    }

    public static function error(string $ruleName, string $message, ?string $suggestion = null, ?int $lineNumber = null): self
    {
        return new self($ruleName, 'error', $message, $suggestion, $lineNumber);
    }

    public static function warning(string $ruleName, string $message, ?string $suggestion = null, ?int $lineNumber = null): self
    {
        return new self($ruleName, 'warning', $message, $suggestion, $lineNumber);
    }

    public static function info(string $ruleName, string $message, ?string $suggestion = null, ?int $lineNumber = null): self
    {
        return new self($ruleName, 'info', $message, $suggestion, $lineNumber);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->ruleName,
            'severity' => $this->severity,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
            'line' => $this->lineNumber,
        ];
    }
}

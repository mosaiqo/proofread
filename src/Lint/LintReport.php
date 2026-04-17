<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint;

final readonly class LintReport
{
    /**
     * @param  list<LintIssue>  $issues
     */
    public function __construct(
        public string $agentClass,
        public string $instructions,
        public array $issues,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errorCount() > 0;
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    /**
     * @return list<LintIssue>
     */
    public function issuesWithSeverity(string $severity): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn (LintIssue $issue): bool => $issue->severity === $severity,
        ));
    }

    public function errorCount(): int
    {
        return count($this->issuesWithSeverity('error'));
    }

    public function warningCount(): int
    {
        return count($this->issuesWithSeverity('warning'));
    }

    public function infoCount(): int
    {
        return count($this->issuesWithSeverity('info'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->agentClass,
            'issues' => array_map(
                static fn (LintIssue $issue): array => $issue->toArray(),
                $this->issues,
            ),
            'summary' => [
                'errors' => $this->errorCount(),
                'warnings' => $this->warningCount(),
                'infos' => $this->infoCount(),
            ],
        ];
    }
}

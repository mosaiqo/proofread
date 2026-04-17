<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint\Rules;

use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Lint\Contracts\LintRule;
use Mosaiqo\Proofread\Lint\LintIssue;

final class MissingRoleRule implements LintRule
{
    private const ROLE_PATTERNS = [
        '/\byou are\b/i',
        '/\bact as\b/i',
        '/\byour role\b/i',
        '/\bas an?\b/i',
    ];

    public function name(): string
    {
        return 'missing_role';
    }

    /**
     * @return list<LintIssue>
     */
    public function check(Agent $agent, string $instructions): array
    {
        $firstParagraph = $this->firstParagraph($instructions);

        foreach (self::ROLE_PATTERNS as $pattern) {
            if (preg_match($pattern, $firstParagraph) === 1) {
                return [];
            }
        }

        return [LintIssue::warning(
            ruleName: $this->name(),
            message: "Instruction does not appear to define the agent's role or persona. Consider starting with 'You are a...' or similar.",
            suggestion: "Open with a role line like 'You are a support agent for ...' to anchor the model.",
        )];
    }

    private function firstParagraph(string $instructions): string
    {
        $parts = preg_split('/\r?\n\r?\n/', $instructions, 2);
        if ($parts === false || $parts === []) {
            return $instructions;
        }

        return $parts[0];
    }
}

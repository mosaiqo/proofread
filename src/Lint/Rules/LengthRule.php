<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint\Rules;

use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Lint\Contracts\LintRule;
use Mosaiqo\Proofread\Lint\LintIssue;

final class LengthRule implements LintRule
{
    public const MIN_LENGTH = 50;

    public const MAX_LENGTH = 10_000;

    public function name(): string
    {
        return 'length';
    }

    /**
     * @return list<LintIssue>
     */
    public function check(Agent $agent, string $instructions): array
    {
        $length = mb_strlen($instructions);

        if ($length < self::MIN_LENGTH) {
            return [LintIssue::warning(
                ruleName: $this->name(),
                message: sprintf(
                    'Instruction is only %d chars. Effective instructions usually provide more context about role, task, and constraints.',
                    $length,
                ),
                suggestion: 'Add more context: who the agent is, what task it performs, and any constraints.',
            )];
        }

        if ($length > self::MAX_LENGTH) {
            return [LintIssue::warning(
                ruleName: $this->name(),
                message: sprintf(
                    'Instruction is %d chars. Consider splitting into sub-agents or using more structured prompts.',
                    $length,
                ),
                suggestion: 'Split the instruction into smaller, focused agents or add structured sections.',
            )];
        }

        return [];
    }
}

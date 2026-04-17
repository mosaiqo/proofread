<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint\Rules;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Mosaiqo\Proofread\Lint\Contracts\LintRule;
use Mosaiqo\Proofread\Lint\LintIssue;

final class MissingOutputFormatRule implements LintRule
{
    private const FORMAT_TOKENS = [
        'json',
        'schema',
        'structured',
        'format',
        'shape',
    ];

    public function name(): string
    {
        return 'missing_output_format';
    }

    /**
     * @return list<LintIssue>
     */
    public function check(Agent $agent, string $instructions): array
    {
        if (! $agent instanceof HasStructuredOutput) {
            return [];
        }

        $lower = mb_strtolower($instructions);
        foreach (self::FORMAT_TOKENS as $token) {
            if (str_contains($lower, $token)) {
                return [];
            }
        }

        return [LintIssue::warning(
            ruleName: $this->name(),
            message: "Agent declares a structured output schema but the instruction doesn't reference JSON or output format. The model may produce free text.",
            suggestion: "Mention the required output format explicitly (e.g. 'respond with JSON matching the provided schema').",
        )];
    }
}

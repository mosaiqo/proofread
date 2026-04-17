<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint\Rules;

use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Lint\Contracts\LintRule;
use Mosaiqo\Proofread\Lint\LintIssue;

final class AmbiguityRule implements LintRule
{
    private const HEDGE_WORDS = [
        'maybe',
        'perhaps',
        'if possible',
        'try to',
        'should try',
        'might want',
        'could potentially',
        'when appropriate',
    ];

    public function name(): string
    {
        return 'ambiguity';
    }

    /**
     * @return list<LintIssue>
     */
    public function check(Agent $agent, string $instructions): array
    {
        $issues = [];
        $lines = preg_split('/\r?\n/', $instructions);
        if ($lines === false) {
            $lines = [$instructions];
        }

        foreach ($lines as $index => $line) {
            foreach (self::HEDGE_WORDS as $hedge) {
                if ($this->containsWordPhrase($line, $hedge)) {
                    $issues[] = LintIssue::info(
                        ruleName: $this->name(),
                        message: sprintf(
                            "Hedging phrase '%s' may cause inconsistent behavior. Consider rephrasing to an explicit rule.",
                            $hedge,
                        ),
                        suggestion: sprintf("Replace '%s' with an explicit instruction or drop the phrase.", $hedge),
                        lineNumber: $index + 1,
                    );
                }
            }
        }

        return $issues;
    }

    private function containsWordPhrase(string $haystack, string $phrase): bool
    {
        $pattern = '/\b'.preg_quote($phrase, '/').'\b/i';

        return preg_match($pattern, $haystack) === 1;
    }
}

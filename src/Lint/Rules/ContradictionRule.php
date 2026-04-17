<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint\Rules;

use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Lint\Contracts\LintRule;
use Mosaiqo\Proofread\Lint\LintIssue;

/**
 * Conservative heuristic for detecting "always X" vs "never X" contradictions.
 *
 * Implementation extracts short phrases following "always" or "never" and
 * compares their lowercased content-word sets. When the shorter phrase's
 * content words are largely contained in the longer one (ratio above 0.5),
 * the rule flags a contradiction. Shorter-phrase containment is used rather
 * than symmetric Jaccard overlap because scoped exceptions ("never X when Y")
 * still contradict the unconditional rule ("always X").
 */
final class ContradictionRule implements LintRule
{
    private const STOPWORDS = [
        'the', 'a', 'an', 'to', 'of', 'in', 'on', 'at', 'for', 'and', 'or',
        'but', 'is', 'are', 'be', 'this', 'that', 'those', 'these', 'with',
        'without', 'by', 'from', 'as', 'it', 'its', 'you', 'your',
    ];

    public function name(): string
    {
        return 'contradiction';
    }

    /**
     * @return list<LintIssue>
     */
    public function check(Agent $agent, string $instructions): array
    {
        $alwaysPhrases = $this->extractPhrases($instructions, 'always');
        $neverPhrases = $this->extractPhrases($instructions, 'never');

        if ($alwaysPhrases === [] || $neverPhrases === []) {
            return [];
        }

        $issues = [];
        foreach ($alwaysPhrases as $always) {
            foreach ($neverPhrases as $never) {
                if ($this->overlapRatio($always, $never) > 0.5) {
                    $issues[] = LintIssue::error(
                        ruleName: $this->name(),
                        message: sprintf(
                            "Potential contradiction: 'always %s' vs 'never %s'. Reconcile these rules.",
                            $always,
                            $never,
                        ),
                        suggestion: 'Remove one rule or scope each to the cases where it applies.',
                    );

                    return $issues;
                }
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function extractPhrases(string $text, string $keyword): array
    {
        $pattern = '/\b'.$keyword.'\s+([^.,;:\n]+)/i';
        if (preg_match_all($pattern, $text, $matches) === false) {
            return [];
        }

        $phrases = [];
        foreach ($matches[1] as $match) {
            $phrases[] = trim((string) $match);
        }

        return $phrases;
    }

    private function overlapRatio(string $a, string $b): float
    {
        $wordsA = $this->contentWords($a);
        $wordsB = $this->contentWords($b);

        if ($wordsA === [] || $wordsB === []) {
            return 0.0;
        }

        $shorter = count($wordsA) <= count($wordsB) ? $wordsA : $wordsB;
        $longer = count($wordsA) <= count($wordsB) ? $wordsB : $wordsA;

        $intersection = array_intersect($shorter, $longer);

        return count($intersection) / count($shorter);
    }

    /**
     * @return list<string>
     */
    private function contentWords(string $phrase): array
    {
        $parts = preg_split('/\s+/', mb_strtolower($phrase));
        if ($parts === false) {
            return [];
        }

        $words = [];
        foreach ($parts as $part) {
            $cleaned = preg_replace('/[^\p{L}\p{N}]+/u', '', $part);
            if (! is_string($cleaned) || $cleaned === '') {
                continue;
            }
            if (in_array($cleaned, self::STOPWORDS, true)) {
                continue;
            }
            $words[] = $cleaned;
        }

        return array_values(array_unique($words));
    }
}

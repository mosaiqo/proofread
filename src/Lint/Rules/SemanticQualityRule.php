<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Lint\Rules;

use Illuminate\Container\Container;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;
use Mosaiqo\Proofread\Judge\Judge;
use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Lint\Contracts\LintRule;
use Mosaiqo\Proofread\Lint\LintIssue;
use Throwable;

/**
 * LLM-based semantic analysis of an Agent's instructions.
 *
 * Calls the Judge agent with a custom prompt that asks for a structured
 * critique (passed, score, reason, issues[]) and converts the response
 * into LintIssues. Designed to be opt-in via the lint command's flag.
 */
final class SemanticQualityRule implements LintRule
{
    private const ERROR_SCORE_THRESHOLD = 0.7;

    private const MAX_ATTEMPTS = 2;

    public function name(): string
    {
        return 'semantic_quality';
    }

    /**
     * @return list<LintIssue>
     */
    public function check(Agent $agent, string $instructions): array
    {
        $prompt = $this->buildPrompt($instructions);

        try {
            $parsed = $this->invokeJudgeAgent($prompt);
        } catch (Throwable $exception) {
            return [LintIssue::warning(
                ruleName: $this->name(),
                message: sprintf('Semantic analysis unavailable: %s', $exception->getMessage()),
            )];
        }

        return $this->issuesFromResponse($parsed);
    }

    /**
     * @return array{passed: bool, score: float, reason: string, issues: list<string>}
     */
    private function invokeJudgeAgent(string $prompt): array
    {
        $model = $this->defaultJudgeModel();
        $judgeAgent = new JudgeAgent;

        $attempts = 0;
        $lastError = 'Judge produced invalid response.';
        while ($attempts < self::MAX_ATTEMPTS) {
            $response = $judgeAgent->prompt($prompt, model: $model);
            try {
                return $this->parseResponse($response->text);
            } catch (InvalidArgumentException $exception) {
                $lastError = $exception->getMessage();
                $attempts++;
            }
        }

        throw new InvalidArgumentException($lastError);
    }

    private function defaultJudgeModel(): string
    {
        $judge = Container::getInstance()->make(Judge::class);

        return $judge->defaultModel();
    }

    private function buildPrompt(string $instructions): string
    {
        return implode("\n", [
            "Evaluate the quality of an AI agent's system instruction.",
            'Check for: clarity, specificity, absence of contradictions, clear',
            'task definition, output format specification.',
            '',
            'INSTRUCTION:',
            $instructions,
            '',
            'Respond with ONLY a JSON object of this exact shape, no preamble:',
            '{"passed": <boolean>, "score": <number between 0 and 1>, "reason": "<one-sentence summary>", "issues": ["<specific issue>", ...]}',
        ]);
    }

    /**
     * @return array{passed: bool, score: float, reason: string, issues: list<string>}
     */
    private function parseResponse(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Empty judge response.');
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Judge response is not a JSON object.');
        }

        if (! array_key_exists('passed', $decoded) || ! is_bool($decoded['passed'])) {
            throw new InvalidArgumentException('Judge response "passed" must be a boolean.');
        }

        $score = $decoded['score'] ?? null;
        if (! is_int($score) && ! is_float($score)) {
            throw new InvalidArgumentException('Judge response "score" must be numeric.');
        }

        $scoreFloat = (float) $score;
        if ($scoreFloat < 0.0 || $scoreFloat > 1.0) {
            throw new InvalidArgumentException('Judge response "score" must be between 0 and 1.');
        }

        $reason = $decoded['reason'] ?? '';
        if (! is_string($reason)) {
            throw new InvalidArgumentException('Judge response "reason" must be a string.');
        }

        $rawIssues = $decoded['issues'] ?? [];
        if (! is_array($rawIssues)) {
            throw new InvalidArgumentException('Judge response "issues" must be an array.');
        }

        $issues = [];
        foreach ($rawIssues as $issue) {
            if (is_string($issue) && $issue !== '') {
                $issues[] = $issue;
            }
        }

        return [
            'passed' => $decoded['passed'],
            'score' => $scoreFloat,
            'reason' => $reason,
            'issues' => $issues,
        ];
    }

    /**
     * @param  array{passed: bool, score: float, reason: string, issues: list<string>}  $parsed
     * @return list<LintIssue>
     */
    private function issuesFromResponse(array $parsed): array
    {
        $issues = [];

        foreach ($parsed['issues'] as $description) {
            $issues[] = LintIssue::warning(
                ruleName: $this->name(),
                message: $description,
            );
        }

        if ($parsed['score'] < self::ERROR_SCORE_THRESHOLD) {
            $issues[] = LintIssue::error(
                ruleName: $this->name(),
                message: sprintf(
                    'Semantic quality score %s is below threshold %s: %s',
                    $this->formatScore($parsed['score']),
                    $this->formatScore(self::ERROR_SCORE_THRESHOLD),
                    $parsed['reason'] !== '' ? $parsed['reason'] : 'low overall quality',
                ),
            );
        }

        return $issues;
    }

    private function formatScore(float $score): string
    {
        $rounded = round($score, 2);
        if ($rounded === floor($rounded)) {
            return number_format($rounded, 1);
        }

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }
}

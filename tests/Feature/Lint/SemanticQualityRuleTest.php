<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Judge\JudgeAgent;
use Mosaiqo\Proofread\Lint\Rules\SemanticQualityRule;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\CleanAgent;

beforeEach(function (): void {
    config()->set('proofread.judge.default_model', 'default-judge');
    config()->set('proofread.judge.max_retries', 1);
    config()->set('ai.default', 'openai');
});

it('returns no issues when judge approves with high score', function (): void {
    JudgeAgent::fake([json_encode([
        'passed' => true,
        'score' => 0.9,
        'reason' => 'Clear and specific',
        'issues' => [],
    ])]);

    $rule = app(SemanticQualityRule::class);
    $agent = new CleanAgent;

    $issues = $rule->check($agent, $agent->instructions());

    expect($issues)->toBe([]);
});

it('returns warnings for issues reported by the judge', function (): void {
    JudgeAgent::fake([json_encode([
        'passed' => true,
        'score' => 0.85,
        'reason' => 'Mostly clear',
        'issues' => [
            'Output format is not specified.',
            'Tone guidance could be more precise.',
        ],
    ])]);

    $rule = app(SemanticQualityRule::class);
    $agent = new CleanAgent;

    $issues = $rule->check($agent, $agent->instructions());

    expect($issues)->toHaveCount(2);
    expect($issues[0]->severity)->toBe('warning');
    expect($issues[0]->ruleName)->toBe('semantic_quality');
    expect($issues[0]->message)->toContain('Output format');
});

it('returns an error when score is below 0.7', function (): void {
    JudgeAgent::fake([json_encode([
        'passed' => false,
        'score' => 0.5,
        'reason' => 'Vague, contradictions present',
        'issues' => ['Contradictions detected'],
    ])]);

    $rule = app(SemanticQualityRule::class);
    $agent = new CleanAgent;

    $issues = $rule->check($agent, $agent->instructions());

    $errors = array_values(array_filter($issues, fn ($i) => $i->severity === 'error'));
    expect($errors)->not->toBe([]);
    expect($errors[0]->message)->toContain('0.5');
});

it('handles judge failures gracefully', function (): void {
    JudgeAgent::fake(['not valid json', 'still not valid']);

    $rule = app(SemanticQualityRule::class);
    $agent = new CleanAgent;

    $issues = $rule->check($agent, $agent->instructions());

    expect($issues)->toHaveCount(1);
    expect($issues[0]->severity)->toBe('warning');
    expect($issues[0]->message)->toContain('Semantic analysis unavailable');
});

<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Lint\Rules\AmbiguityRule;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\CleanAgent;

it('flags hedge words', function (): void {
    $rule = new AmbiguityRule;
    $agent = new CleanAgent;

    $issues = $rule->check(
        $agent,
        'You are helpful. Maybe suggest related products.'
    );

    expect($issues)->not->toBe([]);
    expect($issues[0]->severity)->toBe('info');
    expect($issues[0]->ruleName)->toBe('ambiguity');
    expect($issues[0]->message)->toContain('maybe');
});

it('reports the line number of the hedge word', function (): void {
    $rule = new AmbiguityRule;
    $agent = new CleanAgent;

    $issues = $rule->check(
        $agent,
        "You are helpful.\nBe polite.\nMaybe suggest related products."
    );

    expect($issues)->not->toBe([]);
    expect($issues[0]->lineNumber)->toBe(3);
});

it('returns no issues for instruction without hedge words', function (): void {
    $rule = new AmbiguityRule;
    $agent = new CleanAgent;

    $issues = $rule->check(
        $agent,
        'You are a support agent. Always confirm order IDs before answering.'
    );

    expect($issues)->toBe([]);
});

<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Lint\Rules\ContradictionRule;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\CleanAgent;

it('flags always/never contradictions', function (): void {
    $rule = new ContradictionRule;
    $agent = new CleanAgent;

    $issues = $rule->check(
        $agent,
        "Always respond in English.\nNever respond in English."
    );

    expect($issues)->not->toBe([]);
    expect($issues[0]->severity)->toBe('error');
    expect($issues[0]->ruleName)->toBe('contradiction');
});

it('returns no issues for non-contradictory always/never rules', function (): void {
    $rule = new ContradictionRule;
    $agent = new CleanAgent;

    $issues = $rule->check(
        $agent,
        "Always confirm order IDs.\nNever share personal data."
    );

    expect($issues)->toBe([]);
});

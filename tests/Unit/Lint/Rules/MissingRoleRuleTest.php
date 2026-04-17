<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Lint\Rules\MissingRoleRule;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\CleanAgent;

it('flags instructions lacking a role opening', function (): void {
    $rule = new MissingRoleRule;
    $agent = new CleanAgent;

    $issues = $rule->check(
        $agent,
        "Answer customer questions politely.\n\nNever reveal pricing."
    );

    expect($issues)->toHaveCount(1);
    expect($issues[0]->severity)->toBe('warning');
    expect($issues[0]->ruleName)->toBe('missing_role');
});

it('accepts instructions starting with a role opening', function (): void {
    $rule = new MissingRoleRule;
    $agent = new CleanAgent;

    $issues = $rule->check(
        $agent,
        "You are a support agent.\n\nBe polite."
    );

    expect($issues)->toBe([]);
});

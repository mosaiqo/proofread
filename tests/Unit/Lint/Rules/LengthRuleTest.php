<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Lint\Rules\LengthRule;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\CleanAgent;

it('flags instructions that are too short', function (): void {
    $rule = new LengthRule;
    $agent = new CleanAgent;

    $issues = $rule->check($agent, 'Summarize.');

    expect($issues)->toHaveCount(1);
    expect($issues[0]->severity)->toBe('warning');
    expect($issues[0]->ruleName)->toBe('length');
    expect($issues[0]->message)->toContain('10');
});

it('flags instructions that are too long', function (): void {
    $rule = new LengthRule;
    $agent = new CleanAgent;

    $issues = $rule->check($agent, str_repeat('a', 10_001));

    expect($issues)->toHaveCount(1);
    expect($issues[0]->severity)->toBe('warning');
    expect($issues[0]->message)->toContain('10001');
});

it('returns no issues for normal length', function (): void {
    $rule = new LengthRule;
    $agent = new CleanAgent;

    $issues = $rule->check($agent, str_repeat('abcdefghij ', 30));

    expect($issues)->toBe([]);
});

<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Lint\Rules\MissingOutputFormatRule;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\CleanAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\StructuredMissingFormatAgent;
use Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures\StructuredWithFormatAgent;

it('flags structured agents whose instructions omit output format', function (): void {
    $rule = new MissingOutputFormatRule;
    $agent = new StructuredMissingFormatAgent;

    $issues = $rule->check($agent, $agent->instructions());

    expect($issues)->toHaveCount(1);
    expect($issues[0]->severity)->toBe('warning');
    expect($issues[0]->ruleName)->toBe('missing_output_format');
});

it('returns no issues when structured agent mentions JSON in instructions', function (): void {
    $rule = new MissingOutputFormatRule;
    $agent = new StructuredWithFormatAgent;

    $issues = $rule->check($agent, $agent->instructions());

    expect($issues)->toBe([]);
});

it('returns no issues when the agent does not declare structured output', function (): void {
    $rule = new MissingOutputFormatRule;
    $agent = new CleanAgent;

    $issues = $rule->check($agent, $agent->instructions());

    expect($issues)->toBe([]);
});

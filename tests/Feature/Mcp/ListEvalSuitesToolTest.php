<?php

declare(strict_types=1);

use Laravel\Mcp\ResponseFactory;
use Mosaiqo\Proofread\Mcp\Tools\ListEvalSuitesTool;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\EmptySuite;
use Mosaiqo\Proofread\Tests\Fixtures\Suites\PassingSuite;

it('returns an empty list when no suites are configured', function (): void {
    config()->set('proofread.mcp.suites', []);

    $tool = new ListEvalSuitesTool;
    $payload = $tool->handlePayload();

    expect($payload)->toBe(['suites' => []]);
});

it('lists all configured suites', function (): void {
    config()->set('proofread.mcp.suites', [
        PassingSuite::class,
        EmptySuite::class,
    ]);

    $tool = new ListEvalSuitesTool;
    $payload = $tool->handlePayload();

    expect($payload['suites'])->toHaveCount(2)
        ->and($payload['suites'][0]['class'])->toBe(PassingSuite::class)
        ->and($payload['suites'][1]['class'])->toBe(EmptySuite::class);
});

it('includes name class case_count and subject type per suite', function (): void {
    config()->set('proofread.mcp.suites', [PassingSuite::class]);

    $tool = new ListEvalSuitesTool;
    $payload = $tool->handlePayload();

    $entry = $payload['suites'][0];
    expect($entry)->toHaveKeys(['name', 'class', 'dataset', 'case_count', 'subject'])
        ->and($entry['name'])->toBe(PassingSuite::class)
        ->and($entry['class'])->toBe(PassingSuite::class)
        ->and($entry['dataset'])->toBe('passing')
        ->and($entry['case_count'])->toBe(2)
        ->and($entry['subject'])->toBe('callable');
});

it('skips classes that do not extend EvalSuite', function (): void {
    config()->set('proofread.mcp.suites', [
        PassingSuite::class,
        stdClass::class,
        'Missing\\NonExistentSuite',
    ]);

    $tool = new ListEvalSuitesTool;
    $payload = $tool->handlePayload();

    expect($payload['suites'])->toHaveCount(1)
        ->and($payload['suites'][0]['class'])->toBe(PassingSuite::class);
});

it('exposes the expected tool name and description', function (): void {
    $tool = new ListEvalSuitesTool;

    expect($tool->name())->toBe('list_eval_suites')
        ->and($tool->description())->toBe(
            'List all Proofread EvalSuite classes available for evaluation.'
        );
});

it('returns a structured MCP response from handle', function (): void {
    config()->set('proofread.mcp.suites', [PassingSuite::class]);

    $tool = new ListEvalSuitesTool;
    $response = $tool->handle();

    expect($response)->toBeInstanceOf(ResponseFactory::class);
});

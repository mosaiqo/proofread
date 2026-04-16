<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Mosaiqo\Proofread\Assertions\Trajectory;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Support\AssertionResult;

/**
 * @param  array<int, string>  $toolCallNames
 * @param  int  $stepCount  Number of steps to synthesize (defaults to max(1, count of tool calls)).
 */
function makeAgentResponse(array $toolCallNames = [], ?int $stepCount = null): AgentResponse
{
    $response = new AgentResponse(
        invocationId: 'inv-test',
        text: 'final output',
        usage: new Usage,
        meta: new Meta('fake-provider', 'fake-model'),
    );

    $toolCalls = [];
    foreach ($toolCallNames as $index => $name) {
        $toolCalls[] = new ToolCall(
            id: 'tc-'.$index,
            name: $name,
            arguments: [],
        );
    }

    $steps = [];
    $stepCount ??= max(1, count($toolCalls));
    for ($i = 0; $i < $stepCount; $i++) {
        $steps[] = new Step(
            text: 'step-'.$i,
            toolCalls: [],
            toolResults: [],
            finishReason: FinishReason::Stop,
            usage: new Usage,
            meta: new Meta('fake-provider', 'fake-model'),
        );
    }

    $response->toolCalls = new Collection($toolCalls);
    $response->steps = new Collection($steps);

    return $response;
}

// --- maxSteps / minSteps / stepsBetween ---

it('passes when step count is within the max limit', function (): void {
    $response = makeAgentResponse(stepCount: 2);

    $result = Trajectory::maxSteps(5)->run('final', ['raw' => $response]);

    expect($result)->toBeInstanceOf(AssertionResult::class)
        ->and($result->passed)->toBeTrue();
});

it('fails when step count exceeds the max', function (): void {
    $response = makeAgentResponse(stepCount: 6);

    $result = Trajectory::maxSteps(5)->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('6')
        ->and($result->reason)->toContain('5');
});

it('passes when step count meets the min', function (): void {
    $response = makeAgentResponse(stepCount: 3);

    $result = Trajectory::minSteps(3)->run('final', ['raw' => $response]);

    expect($result->passed)->toBeTrue();
});

it('fails when step count is below the min', function (): void {
    $response = makeAgentResponse(stepCount: 1);

    $result = Trajectory::minSteps(3)->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('1')
        ->and($result->reason)->toContain('3');
});

it('passes when step count is within the range', function (): void {
    $response = makeAgentResponse(stepCount: 3);

    $result = Trajectory::stepsBetween(2, 4)->run('final', ['raw' => $response]);

    expect($result->passed)->toBeTrue();
});

it('fails when step count is below the range', function (): void {
    $response = makeAgentResponse(stepCount: 1);

    $result = Trajectory::stepsBetween(2, 4)->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse();
});

it('fails when step count is above the range', function (): void {
    $response = makeAgentResponse(stepCount: 5);

    $result = Trajectory::stepsBetween(2, 4)->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse();
});

it('rejects negative limits', function (): void {
    Trajectory::maxSteps(-1);
})->throws(InvalidArgumentException::class);

it('rejects negative min', function (): void {
    Trajectory::minSteps(-1);
})->throws(InvalidArgumentException::class);

it('rejects inverted range (min > max)', function (): void {
    Trajectory::stepsBetween(5, 2);
})->throws(InvalidArgumentException::class);

it('rejects negative values in stepsBetween', function (): void {
    Trajectory::stepsBetween(-1, 2);
})->throws(InvalidArgumentException::class);

// --- callsTool / doesNotCallTool ---

it('passes when a required tool was called', function (): void {
    $response = makeAgentResponse(['search', 'summarize']);

    $result = Trajectory::callsTool('search')->run('final', ['raw' => $response]);

    expect($result->passed)->toBeTrue();
});

it('fails when a required tool was not called', function (): void {
    $response = makeAgentResponse(['summarize']);

    $result = Trajectory::callsTool('search')->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('search');
});

it('passes when a forbidden tool was not called', function (): void {
    $response = makeAgentResponse(['summarize']);

    $result = Trajectory::doesNotCallTool('delete_all')->run('final', ['raw' => $response]);

    expect($result->passed)->toBeTrue();
});

it('fails when a forbidden tool was called', function (): void {
    $response = makeAgentResponse(['summarize', 'delete_all']);

    $result = Trajectory::doesNotCallTool('delete_all')->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('delete_all');
});

it('rejects empty tool name in callsTool', function (): void {
    Trajectory::callsTool('');
})->throws(InvalidArgumentException::class);

it('rejects empty tool name in doesNotCallTool', function (): void {
    Trajectory::doesNotCallTool('');
})->throws(InvalidArgumentException::class);

// --- callsTools (all of, any order) ---

it('passes when all required tools were called in any order', function (): void {
    $response = makeAgentResponse(['b', 'a', 'c']);

    $result = Trajectory::callsTools(['a', 'b'])->run('final', ['raw' => $response]);

    expect($result->passed)->toBeTrue();
});

it('fails listing missing tools', function (): void {
    $response = makeAgentResponse(['a']);

    $result = Trajectory::callsTools(['a', 'b', 'c'])->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('b')
        ->and($result->reason)->toContain('c');
});

it('rejects empty tool list in callsTools', function (): void {
    Trajectory::callsTools([]);
})->throws(InvalidArgumentException::class);

it('rejects non-string tool names in callsTools', function (): void {
    /** @phpstan-ignore-next-line */
    Trajectory::callsTools(['valid', 42]);
})->throws(InvalidArgumentException::class);

it('rejects empty tool names inside callsTools list', function (): void {
    Trajectory::callsTools(['valid', '']);
})->throws(InvalidArgumentException::class);

// --- callsToolsInOrder (subsequence) ---

it('passes when tools appear in order', function (): void {
    $response = makeAgentResponse(['a', 'b', 'c']);

    $result = Trajectory::callsToolsInOrder(['a', 'b'])->run('final', ['raw' => $response]);

    expect($result->passed)->toBeTrue();
});

it('passes even with other tools interleaved', function (): void {
    $response = makeAgentResponse(['a', 'x', 'b', 'y']);

    $result = Trajectory::callsToolsInOrder(['a', 'b'])->run('final', ['raw' => $response]);

    expect($result->passed)->toBeTrue();
});

it('fails when order is wrong', function (): void {
    $response = makeAgentResponse(['b', 'a']);

    $result = Trajectory::callsToolsInOrder(['a', 'b'])->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse();
});

it('fails when a tool is missing in callsToolsInOrder', function (): void {
    $response = makeAgentResponse(['a']);

    $result = Trajectory::callsToolsInOrder(['a', 'b'])->run('final', ['raw' => $response]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('b');
});

it('rejects empty tool list in callsToolsInOrder', function (): void {
    Trajectory::callsToolsInOrder([]);
})->throws(InvalidArgumentException::class);

it('rejects non-string tool names in callsToolsInOrder', function (): void {
    /** @phpstan-ignore-next-line */
    Trajectory::callsToolsInOrder(['a', 42]);
})->throws(InvalidArgumentException::class);

it('rejects empty tool names inside callsToolsInOrder list', function (): void {
    Trajectory::callsToolsInOrder(['a', '']);
})->throws(InvalidArgumentException::class);

// --- Contract + error cases ---

it('fails when context has no raw response', function (): void {
    $result = Trajectory::maxSteps(5)->run('final', []);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('Trajectory requires an Agent subject');
});

it('fails when context raw is not an AgentResponse-like object', function (): void {
    $result = Trajectory::maxSteps(5)->run('final', ['raw' => 'not-a-response']);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('Trajectory requires an Agent subject');
});

it('fails cleanly when the raw is an array (callable subject)', function (): void {
    $result = Trajectory::callsTool('search')->run('final', ['raw' => ['foo' => 'bar']]);

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('Trajectory requires an Agent subject');
});

it('exposes name as "trajectory"', function (): void {
    expect(Trajectory::maxSteps(1)->name())->toBe('trajectory');
});

it('implements the Assertion contract', function (): void {
    expect(Trajectory::maxSteps(1))->toBeInstanceOf(Assertion::class);
});

it('returns an AssertionResult', function (): void {
    $response = makeAgentResponse(stepCount: 1);

    expect(Trajectory::maxSteps(5)->run('final', ['raw' => $response]))
        ->toBeInstanceOf(AssertionResult::class);
});

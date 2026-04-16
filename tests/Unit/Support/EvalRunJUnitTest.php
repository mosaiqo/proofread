<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;

/**
 * @param  array<string, mixed>  $case
 * @param  list<AssertionResult>  $assertionResults
 */
function junitResult(
    array $case = ['input' => 'x'],
    mixed $output = 'x',
    array $assertionResults = [],
    float $durationMs = 100.0,
    ?Throwable $error = null,
): EvalResult {
    return EvalResult::make($case, $output, $assertionResults, $durationMs, $error);
}

/**
 * @return array{DOMDocument, DOMXPath}
 */
function parseJUnit(string $xml): array
{
    $doc = new DOMDocument;
    libxml_use_internal_errors(true);
    $loaded = $doc->loadXML($xml);
    expect($loaded)->toBeTrue();
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    return [$doc, new DOMXPath($doc)];
}

/**
 * @return DOMNodeList<DOMNameSpaceNode|DOMNode>
 */
function xpathQuery(DOMXPath $xpath, string $query): DOMNodeList
{
    $nodes = $xpath->query($query);
    if (! $nodes instanceof DOMNodeList) {
        throw new RuntimeException('XPath query failed: '.$query);
    }

    return $nodes;
}

function xpathElement(DOMXPath $xpath, string $query, int $offset = 0): DOMElement
{
    $node = xpathQuery($xpath, $query)->item($offset);
    if (! $node instanceof DOMElement) {
        throw new RuntimeException('XPath did not return an element: '.$query);
    }

    return $node;
}

it('produces valid XML', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [junitResult(['input' => 'a'])], 1.5);

    $doc = new DOMDocument;
    libxml_use_internal_errors(true);
    $ok = $doc->loadXML($run->toJUnitXml());
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors(false);

    expect($ok)->toBeTrue();
    expect($errors)->toBe([]);
});

it('declares UTF-8 encoding', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [junitResult()], 1.0);

    expect($run->toJUnitXml())->toContain('encoding="UTF-8"');
});

it('has a root <testsuites> element with aggregate counts', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b'], ['input' => 'c']]);
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'a'], 'a', [AssertionResult::pass()]),
        junitResult(['input' => 'b'], 'b', [AssertionResult::fail('boom')]),
        junitResult(['input' => 'c'], null, [], 0.0, new RuntimeException('err')),
    ], 542.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $root = xpathElement($xpath, '/testsuites');
    expect($root->getAttribute('name'))->toBe('proofread');
    expect($root->getAttribute('tests'))->toBe('3');
    expect($root->getAttribute('failures'))->toBe('1');
    expect($root->getAttribute('errors'))->toBe('1');
    expect($root->getAttribute('time'))->toBe('0.542');
});

it('contains a single <testsuite> with the dataset name', function (): void {
    $dataset = Dataset::make('sentiment-classification', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [junitResult()], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathQuery($xpath, '/testsuites/testsuite')->length)->toBe(1);
    expect(xpathElement($xpath, '/testsuites/testsuite')->getAttribute('name'))
        ->toBe('sentiment-classification');
});

it('includes a <properties> block with dataset name', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [junitResult()], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $prop = xpathElement($xpath, '/testsuites/testsuite/properties/property[@name="proofread.dataset"]');
    expect($prop->getAttribute('value'))->toBe('d');
});

it('aggregates total_cost_usd when at least one result has cost', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b']]);
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'a'], 'a', [
            AssertionResult::pass('', null, ['cost_usd' => 0.001]),
        ]),
        junitResult(['input' => 'b'], 'b', [
            AssertionResult::pass('', null, ['cost_usd' => 0.0012]),
        ]),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $prop = xpathElement($xpath, '/testsuites/testsuite/properties/property[@name="proofread.total_cost_usd"]');
    expect($prop->getAttribute('value'))->toBe('0.0022');
});

it('omits cost property when no result reports cost', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [junitResult()], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathQuery($xpath, '/testsuites/testsuite/properties/property[@name="proofread.total_cost_usd"]')->length)
        ->toBe(0);
});

it('aggregates total_tokens_in when reported', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b']]);
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'a'], 'a', [
            AssertionResult::pass('', null, ['tokens_in' => 100]),
        ]),
        junitResult(['input' => 'b'], 'b', [
            AssertionResult::pass('', null, ['tokens_in' => 50]),
        ]),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $prop = xpathElement($xpath, '/testsuites/testsuite/properties/property[@name="proofread.total_tokens_in"]');
    expect($prop->getAttribute('value'))->toBe('150');
});

it('aggregates total_tokens_out when reported', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'a'], 'a', [
            AssertionResult::pass('', null, ['tokens_out' => 33]),
        ]),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $prop = xpathElement($xpath, '/testsuites/testsuite/properties/property[@name="proofread.total_tokens_out"]');
    expect($prop->getAttribute('value'))->toBe('33');
});

it('emits one <testcase> per result', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b'], ['input' => 'c']]);
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'a']),
        junitResult(['input' => 'b']),
        junitResult(['input' => 'c']),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathQuery($xpath, '/testsuites/testsuite/testcase')->length)->toBe(3);
});

it('names testcases with case_{i} by default', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a'], ['input' => 'b']]);
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'a']),
        junitResult(['input' => 'b']),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathElement($xpath, '/testsuites/testsuite/testcase', 0)->getAttribute('name'))
        ->toBe('case_0');
    expect(xpathElement($xpath, '/testsuites/testsuite/testcase', 1)->getAttribute('name'))
        ->toBe('case_1');
});

it('uses meta.name for testcase name when present', function (): void {
    $dataset = Dataset::make('d', [
        ['input' => 'a', 'meta' => ['name' => 'greet-happy-path']],
        ['input' => 'b'],
    ]);
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'a', 'meta' => ['name' => 'greet-happy-path']]),
        junitResult(['input' => 'b']),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathElement($xpath, '/testsuites/testsuite/testcase', 0)->getAttribute('name'))
        ->toBe('greet-happy-path');
    expect(xpathElement($xpath, '/testsuites/testsuite/testcase', 1)->getAttribute('name'))
        ->toBe('case_1');
});

it('sanitizes the dataset name in classname', function (): void {
    $dataset = Dataset::make('with spaces here', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [junitResult()], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathElement($xpath, '/testsuites/testsuite/testcase')->getAttribute('classname'))
        ->toBe('proofread.with_spaces_here');
});

it('emits a <failure> element for failed assertion cases', function (): void {
    $dataset = Dataset::make('d', [['input' => 'This is terrible.']]);
    $run = EvalRun::make($dataset, [
        junitResult(
            ['input' => 'This is terrible.'],
            'negative',
            [AssertionResult::fail('Output does not contain "positive"', null, ['assertion_name' => 'contains'])],
            200.0,
        ),
    ], 200.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathQuery($xpath, '/testsuites/testsuite/testcase/failure')->length)->toBe(1);
});

it('includes the assertion name in the failure message', function (): void {
    $dataset = Dataset::make('d', [['input' => 'x']]);
    $run = EvalRun::make($dataset, [
        junitResult(
            ['input' => 'x'],
            'y',
            [AssertionResult::fail('Output does not contain "positive"', null, ['assertion_name' => 'contains'])],
        ),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $failure = xpathElement($xpath, '/testsuites/testsuite/testcase/failure');
    expect($failure->getAttribute('message'))->toBe('contains: Output does not contain "positive"');
    expect($failure->getAttribute('type'))->toBe('AssertionFailure');
});

it('summarizes multiple failed assertions in the failure message', function (): void {
    $dataset = Dataset::make('d', [['input' => 'x']]);
    $run = EvalRun::make($dataset, [
        junitResult(
            ['input' => 'x'],
            'y',
            [
                AssertionResult::fail('first reason', null, ['assertion_name' => 'first_name']),
                AssertionResult::fail('second reason', null, ['assertion_name' => 'second_name']),
            ],
        ),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $failure = xpathElement($xpath, '/testsuites/testsuite/testcase/failure');
    expect($failure->getAttribute('message'))->toBe('first_name: first reason (+1 more)');
});

it('includes full failure detail in the failure body', function (): void {
    $dataset = Dataset::make('d', [['input' => 'This is terrible.']]);
    $run = EvalRun::make($dataset, [
        junitResult(
            ['input' => 'This is terrible.'],
            'negative',
            [
                AssertionResult::fail('Output does not contain "positive"', null, ['assertion_name' => 'contains']),
                AssertionResult::fail('Output length below min', null, ['assertion_name' => 'length']),
            ],
        ),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $body = xpathElement($xpath, '/testsuites/testsuite/testcase/failure')->textContent;
    expect($body)->toContain('case_0 failed 2 assertions:');
    expect($body)->toContain('contains: Output does not contain "positive"');
    expect($body)->toContain('length: Output length below min');
    expect($body)->toContain('input: "This is terrible."');
    expect($body)->toContain('output: "negative"');
});

it('truncates long inputs in failure body', function (): void {
    $longInput = str_repeat('A', 400);
    $dataset = Dataset::make('d', [['input' => $longInput]]);
    $run = EvalRun::make($dataset, [
        junitResult(
            ['input' => $longInput],
            'out',
            [AssertionResult::fail('bad', null, ['assertion_name' => 'contains'])],
        ),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $body = xpathElement($xpath, '/testsuites/testsuite/testcase/failure')->textContent;
    expect($body)->toContain(str_repeat('A', 200));
    expect($body)->not->toContain(str_repeat('A', 250));
});

it('emits an <error> element when the case raised an exception', function (): void {
    $dataset = Dataset::make('d', [['input' => 'x']]);
    $err = new RuntimeException('boom');
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'x'], null, [], 0.0, $err),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathQuery($xpath, '/testsuites/testsuite/testcase/error')->length)->toBe(1);
    expect(xpathElement($xpath, '/testsuites/testsuite/testcase/error')->getAttribute('message'))
        ->toBe('boom');
});

it('uses the exception class as the error type', function (): void {
    $dataset = Dataset::make('d', [['input' => 'x']]);
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'x'], null, [], 0.0, new LogicException('bad')),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathElement($xpath, '/testsuites/testsuite/testcase/error')->getAttribute('type'))
        ->toBe('LogicException');
});

it('includes the stack trace in the error body', function (): void {
    $dataset = Dataset::make('d', [['input' => 'x']]);
    $err = new RuntimeException('boom');
    $run = EvalRun::make($dataset, [
        junitResult(['input' => 'x'], null, [], 0.0, $err),
    ], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $body = xpathElement($xpath, '/testsuites/testsuite/testcase/error')->textContent;
    expect($body)->toContain('RuntimeException: boom');
    expect($body)->toContain($err->getTraceAsString());
});

it('handles an empty dataset correctly', function (): void {
    $dataset = Dataset::make('d', []);
    $run = EvalRun::make($dataset, [], 0.0);

    [$doc, $xpath] = parseJUnit($run->toJUnitXml());
    expect($doc)->toBeInstanceOf(DOMDocument::class);

    $root = xpathElement($xpath, '/testsuites');
    expect($root->getAttribute('tests'))->toBe('0');
    expect($root->getAttribute('failures'))->toBe('0');
    expect($root->getAttribute('errors'))->toBe('0');

    expect(xpathQuery($xpath, '/testsuites/testsuite/testcase')->length)->toBe(0);
});

it('escapes XML-unsafe characters in messages', function (): void {
    $dataset = Dataset::make('d', [['input' => '<danger>']]);
    $run = EvalRun::make($dataset, [
        junitResult(
            ['input' => '<danger>'],
            '"quoted" & more',
            [AssertionResult::fail('reason with <tag> & "quote"', null, ['assertion_name' => 'contains'])],
        ),
    ], 1.0);

    $xml = $run->toJUnitXml();

    [, $xpath] = parseJUnit($xml);

    $failure = xpathElement($xpath, '/testsuites/testsuite/testcase/failure');
    expect($failure->getAttribute('message'))->toBe('contains: reason with <tag> & "quote"');
});

it('uses ISO-8601 UTC for the timestamp attribute', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [junitResult()], 1.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    $timestamp = xpathElement($xpath, '/testsuites/testsuite')->getAttribute('timestamp');
    expect($timestamp)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/');
});

it('formats time attributes in seconds with 3 decimals', function (): void {
    $dataset = Dataset::make('d', [['input' => 'a']]);
    $run = EvalRun::make($dataset, [junitResult(['input' => 'a'], 'a', [], 180.0)], 542.0);

    [, $xpath] = parseJUnit($run->toJUnitXml());

    expect(xpathElement($xpath, '/testsuites')->getAttribute('time'))->toBe('0.542');
    expect(xpathElement($xpath, '/testsuites/testsuite')->getAttribute('time'))->toBe('0.542');
    expect(xpathElement($xpath, '/testsuites/testsuite/testcase')->getAttribute('time'))->toBe('0.180');
});

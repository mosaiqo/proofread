<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;
use Pest\Expectation;
use PHPUnit\Framework\Assert;

expect()->extend('toPassAssertion', function (Assertion $assertion) {
    /** @var Expectation<mixed> $this */
    $result = $assertion->run($this->value);

    Assert::assertTrue(
        $result->passed,
        sprintf(
            'Failed asserting that output passes assertion [%s]: %s',
            $assertion->name(),
            $result->reason,
        )
    );

    return $this;
});

expect()->extend('toPassEval', function (Dataset $dataset, array $assertions = []) {
    /** @var Expectation<mixed> $this */
    $subject = $this->value;

    $subjectIsCallable = is_callable($subject);
    Assert::assertTrue(
        $subjectIsCallable,
        sprintf(
            'toPassEval expects a callable subject, got %s. Non-callable subjects are not supported yet.',
            get_debug_type($subject),
        )
    );

    /** @var callable(mixed, array<string, mixed>): mixed $subject */
    $runner = new EvalRunner;
    $run = $runner->run($subject, $dataset, $assertions);

    Assert::assertTrue(
        $run->passed(),
        $run->passed() ? '' : proofread_format_eval_failure($run, $assertions),
    );

    return $this;
});

/**
 * @param  array<int, Assertion>  $assertions
 */
function proofread_format_eval_failure(EvalRun $run, array $assertions): string
{
    $failures = $run->failures();
    $total = $run->total();
    $failedCount = count($failures);
    $dataset = $run->dataset;

    $header = sprintf(
        'Expected eval "%s" to pass, but %d of %d cases failed:',
        $dataset->name,
        $failedCount,
        $total,
    );

    $detailed = array_slice($failures, 0, 3);
    $lines = [$header];

    foreach ($detailed as $failure) {
        $lines[] = proofread_format_eval_failure_entry($run, $failure, $assertions);
    }

    $extra = $failedCount - count($detailed);
    if ($extra > 0) {
        $lines[] = sprintf('  ... and %d more failures', $extra);
    }

    return implode("\n", $lines);
}

/**
 * @param  array<int, Assertion>  $assertions
 */
function proofread_format_eval_failure_entry(EvalRun $run, EvalResult $failure, array $assertions): string
{
    $index = array_search($failure, $run->results, true);
    $indexLabel = $index === false ? '?' : (string) $index;

    $input = $failure->case['input'] ?? null;
    $inputRepr = proofread_stringify_input($input);

    $lines = [sprintf('  [%s] input=%s', $indexLabel, $inputRepr)];

    if ($failure->hasError()) {
        $error = $failure->error;
        $lines[] = sprintf(
            '      error: %s: %s',
            $error !== null ? $error::class : 'Error',
            $error?->getMessage() ?? '',
        );
    }

    foreach ($failure->assertionResults as $i => $assertionResult) {
        if ($assertionResult->passed) {
            continue;
        }
        $name = $assertions[$i] ?? null;
        $lines[] = $name instanceof Assertion
            ? sprintf('      %s: %s', $name->name(), $assertionResult->reason)
            : sprintf('      %s', $assertionResult->reason);
    }

    return implode("\n", $lines);
}

function proofread_stringify_input(mixed $input): string
{
    $repr = match (true) {
        is_string($input) => sprintf('"%s"', $input),
        is_scalar($input) => var_export($input, true),
        $input === null => 'null',
        default => get_debug_type($input),
    };

    $max = 80;
    if (mb_strlen($repr) > $max) {
        return mb_substr($repr, 0, $max - 3).'...';
    }

    return $repr;
}

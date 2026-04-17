<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Assertions\GoldenSnapshot;
use Mosaiqo\Proofread\Assertions\JsonSchemaAssertion;
use Mosaiqo\Proofread\Assertions\Rubric;
use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Runner\EvalRunner;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;
use Mosaiqo\Proofread\Support\EvalResult;
use Mosaiqo\Proofread\Support\EvalRun;
use Mosaiqo\Proofread\Support\JudgeResult;
use Pest\Expectation;
use Pest\TestSuite;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;

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

expect()->extend('toPassRubric', function (string $criteria, array $options = []) {
    /** @var Expectation<mixed> $this */
    $rubric = Rubric::make($criteria);

    $model = $options['model'] ?? null;
    if (is_string($model) && $model !== '') {
        $rubric = $rubric->using($model);
    }

    $minScore = $options['min_score'] ?? null;
    if (is_int($minScore) || is_float($minScore)) {
        $rubric = $rubric->minScore((float) $minScore);
    }

    $context = array_key_exists('input', $options) ? ['input' => $options['input']] : [];

    $result = $rubric->run($this->value, $context);

    Assert::assertTrue(
        $result->passed,
        proofread_format_rubric_failure($criteria, $result),
    );

    return $this;
});

expect()->extend('toCostUnder', function (float $maxUsd) {
    /** @var Expectation<mixed> $this */
    $subject = $this->value;

    Assert::assertTrue(
        $subject instanceof EvalRun,
        sprintf(
            'toCostUnder expects an %s subject, got %s',
            EvalRun::class,
            get_debug_type($subject),
        ),
    );

    /** @var EvalRun $subject */
    [$total, $hasAnyCost] = proofread_collect_run_cost($subject);

    Assert::assertTrue(
        $hasAnyCost,
        'No cost tracking in this run — subject(s) may not report cost',
    );

    Assert::assertLessThanOrEqual(
        $maxUsd,
        $total,
        sprintf(
            'Total cost %s exceeds limit of %s',
            proofread_format_usd($total),
            proofread_format_usd($maxUsd),
        ),
    );

    return $this;
});

expect()->extend('toMatchSchema', function (array|string $schema) {
    /** @var Expectation<mixed> $this */
    $assertion = proofread_build_schema_assertion($schema);

    $result = $assertion->run($this->value);

    Assert::assertTrue(
        $result->passed,
        sprintf('Failed asserting that output matches schema: %s', $result->reason),
    );

    return $this;
});

expect()->extend('toMatchGoldenSnapshot', function (?string $key = null) {
    /** @var Expectation<mixed> $this */
    $resolvedKey = $key ?? proofread_derive_snapshot_key();

    if ($resolvedKey === null) {
        Assert::fail(
            'toMatchGoldenSnapshot could not derive a snapshot key from the test context; pass an explicit key.',
        );
    }

    $assertion = GoldenSnapshot::forKey($resolvedKey);
    $result = $assertion->run($this->value);

    Assert::assertTrue(
        $result->passed,
        sprintf(
            'Failed asserting that output matches golden snapshot [%s]: %s',
            $resolvedKey,
            $result->reason,
        ),
    );

    return $this;
});

expect()->extend('toPassSuite', function () {
    /** @var Expectation<mixed> $this */
    $subject = $this->value;

    if (! $subject instanceof EvalSuite) {
        throw new ExpectationFailedException(sprintf(
            'toPassSuite expects an EvalSuite instance, got %s',
            get_debug_type($subject),
        ));
    }

    /** @var EvalRunner $runner */
    $runner = app(EvalRunner::class);

    try {
        $run = $runner->runSuite($subject);
    } catch (InvalidArgumentException $exception) {
        throw new ExpectationFailedException(sprintf(
            'toPassSuite could not run suite "%s": %s',
            $subject->name(),
            $exception->getMessage(),
        ));
    }

    Assert::assertTrue(
        $run->passed(),
        $run->passed() ? '' : proofread_format_suite_failure($subject, $run),
    );

    $this->value = $run;

    return $this;
});

expect()->extend('toPassEval', function (Dataset $dataset, array $assertions = []) {
    /** @var Expectation<mixed> $this */
    $subject = $this->value;

    $runner = new EvalRunner;

    try {
        $run = $runner->run($subject, $dataset, $assertions);
    } catch (InvalidArgumentException $exception) {
        throw new ExpectationFailedException(sprintf(
            'toPassEval could not resolve the subject: %s',
            $exception->getMessage(),
        ));
    }

    Assert::assertTrue(
        $run->passed(),
        $run->passed() ? '' : proofread_format_eval_failure($run, $assertions),
    );

    $this->value = $run;

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

function proofread_format_suite_failure(EvalSuite $suite, EvalRun $run): string
{
    $failures = $run->failures();
    $total = $run->total();
    $failedCount = count($failures);

    $header = sprintf(
        'Expected suite "%s" to pass, but %d of %d cases failed:',
        $suite->name(),
        $failedCount,
        $total,
    );

    $detailed = array_slice($failures, 0, 3);
    $lines = [$header];

    foreach ($detailed as $failure) {
        $lines[] = proofread_format_suite_failure_entry($run, $failure);
    }

    $extra = $failedCount - count($detailed);
    if ($extra > 0) {
        $lines[] = sprintf('  ... and %d more failures', $extra);
    }

    return implode("\n", $lines);
}

function proofread_format_suite_failure_entry(EvalRun $run, EvalResult $failure): string
{
    $index = array_search($failure, $run->results, true);
    $indexLabel = $index === false ? '?' : (string) $index;

    $meta = $failure->case['meta'] ?? null;
    $caseName = null;
    if (is_array($meta)) {
        $name = $meta['name'] ?? null;
        if (is_string($name) && $name !== '') {
            $caseName = $name;
        }
    }

    $label = $caseName !== null
        ? sprintf('  [%s] %s', $indexLabel, $caseName)
        : sprintf('  [%s]', $indexLabel);

    $lines = [$label];

    if ($failure->hasError()) {
        $error = $failure->error;
        $lines[] = sprintf(
            '      error: %s: %s',
            $error !== null ? $error::class : 'Error',
            proofread_truncate_reason($error?->getMessage() ?? ''),
        );
    }

    foreach ($failure->assertionResults as $assertionResult) {
        if ($assertionResult->passed) {
            continue;
        }

        $name = $assertionResult->metadata['assertion_name'] ?? null;
        $reason = proofread_truncate_reason($assertionResult->reason);

        $lines[] = is_string($name) && $name !== ''
            ? sprintf('      %s: %s', $name, $reason)
            : sprintf('      %s', $reason);
    }

    return implode("\n", $lines);
}

function proofread_truncate_reason(string $reason): string
{
    $max = 80;
    if (mb_strlen($reason) > $max) {
        return mb_substr($reason, 0, $max - 3).'...';
    }

    return $reason;
}

function proofread_format_rubric_failure(string $criteria, JudgeResult $result): string
{
    $truncatedCriteria = mb_strlen($criteria) > 80
        ? mb_substr($criteria, 0, 77).'...'
        : $criteria;

    $scoreRepr = $result->score === null ? 'n/a' : (string) $result->score;

    $lines = [
        sprintf('Failed asserting that output passes rubric "%s".', $truncatedCriteria),
        sprintf('  judge: %s', $result->judgeModel),
        sprintf('  score: %s', $scoreRepr),
        sprintf('  reason: %s', $result->reason),
    ];

    if ($result->retryCount > 0) {
        $lines[] = sprintf('  retry_count: %d', $result->retryCount);
    }

    return implode("\n", $lines);
}

/**
 * @return array{0: float, 1: bool}
 */
function proofread_collect_run_cost(EvalRun $run): array
{
    $total = 0.0;
    $hasAnyCost = false;

    foreach ($run->results as $result) {
        foreach ($result->assertionResults as $assertion) {
            if (! array_key_exists('cost_usd', $assertion->metadata)) {
                continue;
            }

            $value = $assertion->metadata['cost_usd'];

            if ($value === null) {
                continue;
            }

            if (! is_int($value) && ! is_float($value)) {
                continue;
            }

            $hasAnyCost = true;
            $total += (float) $value;
        }
    }

    return [$total, $hasAnyCost];
}

function proofread_format_usd(float $value): string
{
    return '$'.number_format($value, 4, '.', '');
}

/**
 * @param  array<string, mixed>|string  $schema
 */
function proofread_build_schema_assertion(array|string $schema): JsonSchemaAssertion
{
    if (is_array($schema)) {
        return JsonSchemaAssertion::fromArray($schema);
    }

    $trimmed = ltrim($schema);
    if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
        return JsonSchemaAssertion::fromJson($schema);
    }

    return JsonSchemaAssertion::fromFile($schema);
}

function proofread_derive_snapshot_key(): ?string
{
    try {
        $test = TestSuite::getInstance()->test;
    } catch (Throwable) {
        return null;
    }

    if ($test === null || ! method_exists($test, 'getPrintableTestCaseMethodName')) {
        return null;
    }

    try {
        $reflection = new ReflectionClass($test);
        $file = $reflection->getFileName();
        $description = $test->getPrintableTestCaseMethodName();
    } catch (Throwable) {
        return null;
    }

    if ($file === false || $description === '') {
        return null;
    }

    $root = getcwd();
    $relative = $file;
    if (is_string($root) && str_starts_with($file, $root)) {
        $relative = ltrim(substr($file, strlen($root)), '/\\');
    }

    $relative = preg_replace('/\.php$/', '', $relative) ?? $relative;

    return $relative.'/'.$description;
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

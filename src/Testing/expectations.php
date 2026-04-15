<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Contracts\Assertion;
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

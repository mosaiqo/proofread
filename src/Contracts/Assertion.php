<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Contracts;

use Mosaiqo\Proofread\Support\AssertionResult;

interface Assertion
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function run(mixed $output, array $context = []): AssertionResult;

    public function name(): string;
}

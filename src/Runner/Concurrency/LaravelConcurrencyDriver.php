<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner\Concurrency;

use Illuminate\Support\Facades\Concurrency;

final class LaravelConcurrencyDriver implements ConcurrencyDriver
{
    public function run(array $tasks): array
    {
        /** @var array<int, mixed> $results */
        $results = Concurrency::run($tasks);

        return $results;
    }
}

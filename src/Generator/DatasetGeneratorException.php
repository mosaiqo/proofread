<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Generator;

use RuntimeException;

final class DatasetGeneratorException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $lastRawResponse = '',
        public readonly int $attempts = 0,
    ) {
        parent::__construct($message);
    }
}

<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Shadow;

use Mosaiqo\Proofread\Shadow\Contracts\RandomNumberProvider;

final class SequenceRandomNumberProvider implements RandomNumberProvider
{
    /** @var list<float> */
    private array $sequence;

    public function __construct(float ...$values)
    {
        $this->sequence = array_values($values);
    }

    public function between01(): float
    {
        if ($this->sequence === []) {
            return 0.0;
        }

        return array_shift($this->sequence);
    }
}

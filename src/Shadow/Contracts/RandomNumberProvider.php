<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow\Contracts;

interface RandomNumberProvider
{
    /**
     * Returns a float in [0.0, 1.0).
     */
    public function between01(): float;
}

<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

use Mosaiqo\Proofread\Shadow\Contracts\RandomNumberProvider;

final class MtRandRandomNumberProvider implements RandomNumberProvider
{
    public function between01(): float
    {
        return mt_rand() / mt_getrandmax();
    }
}

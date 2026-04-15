<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests;

use Mosaiqo\Proofread\ProofreadServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ProofreadServiceProvider::class,
        ];
    }
}

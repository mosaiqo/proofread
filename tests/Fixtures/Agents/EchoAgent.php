<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class EchoAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'Echo the user input back verbatim.';
    }
}

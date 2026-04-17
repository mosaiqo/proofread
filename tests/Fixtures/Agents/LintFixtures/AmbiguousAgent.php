<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class AmbiguousAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return "You are a support agent for an online store.\n".
            "Answer the customer politely.\n".
            'Maybe suggest related products if possible, and try to upsell when appropriate.';
    }
}

<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class ContradictoryAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return "You are a multilingual assistant for global customers.\n".
            "Always respond in English.\n".
            'Never respond in English when the user writes in another language.';
    }
}

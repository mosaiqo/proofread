<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Examples;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class ExampleAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            You are a sentiment classifier.
            Classify the user message as exactly one of: positive, negative, neutral.
            Respond with a single lowercase word. Do not include punctuation or any other text.
            INSTRUCTIONS;
    }
}

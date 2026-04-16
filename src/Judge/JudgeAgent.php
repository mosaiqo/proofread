<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Judge;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class JudgeAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a rigorous evaluator. Judge whether the OUTPUT satisfies the CRITERIA. '
            .'Respond with ONLY a JSON object of exact shape '
            .'{"passed": <boolean>, "score": <number between 0 and 1>, "reason": "<one sentence explanation>"} '
            .'with no preamble, commentary, or code fences.';
    }
}

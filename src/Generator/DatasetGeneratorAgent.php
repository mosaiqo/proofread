<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Generator;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class DatasetGeneratorAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You generate synthetic evaluation datasets for AI systems. '
            .'Given a purpose and a JSON Schema, produce diverse, realistic test cases '
            .'that match the schema. Mix typical, edge, ambiguous, and adversarial '
            .'variants. Respond with ONLY a JSON array of case objects, each shaped as '
            .'{"input": <value>, "expected": <optional>, "meta": {"name": "<short name>"}}. '
            .'No preamble, no commentary, no code fences.';
    }
}

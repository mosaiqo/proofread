<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

final class StructuredClassifierAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Classify sentiment as positive, negative, or neutral.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sentiment' => $schema->string()->enum(['positive', 'negative', 'neutral'])->required(),
            'confidence' => $schema->number(),
        ];
    }
}

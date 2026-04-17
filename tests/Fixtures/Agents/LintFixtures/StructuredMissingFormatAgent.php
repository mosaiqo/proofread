<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Agents\LintFixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

final class StructuredMissingFormatAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a sentiment classifier. Read the review and decide if it is positive, negative, or neutral.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sentiment' => $schema->string()->enum(['positive', 'negative', 'neutral'])->required(),
        ];
    }
}

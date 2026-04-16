<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Shadow;

use Closure;
use InvalidArgumentException;
use Mosaiqo\Proofread\Contracts\Assertion;

final class ShadowAssertionsRegistry
{
    /**
     * @var array<class-string, Closure>
     */
    private array $resolvers = [];

    public function register(string $agentClass, Closure $resolver): void
    {
        if (trim($agentClass) === '') {
            throw new InvalidArgumentException('Agent class cannot be empty.');
        }

        /** @var class-string $agentClass */
        $this->resolvers[$agentClass] = $resolver;
    }

    public function hasAssertionsFor(string $agentClass): bool
    {
        return isset($this->resolvers[$agentClass]);
    }

    /**
     * @return list<Assertion>
     */
    public function forAgent(string $agentClass): array
    {
        if (! isset($this->resolvers[$agentClass])) {
            throw new ShadowAssertionsNotRegisteredException(
                "No shadow assertions registered for agent class [{$agentClass}]."
            );
        }

        $assertions = ($this->resolvers[$agentClass])();

        return $this->validateAndNormalize($agentClass, $assertions);
    }

    /**
     * @return list<class-string>
     */
    public function registeredAgents(): array
    {
        return array_keys($this->resolvers);
    }

    /**
     * @return list<Assertion>
     */
    private function validateAndNormalize(string $agentClass, mixed $assertions): array
    {
        if (! is_array($assertions)) {
            throw new InvalidArgumentException(
                "Shadow assertions resolver for [{$agentClass}] must return an array."
            );
        }

        $normalized = [];
        $i = 0;
        foreach ($assertions as $assertion) {
            if (! $assertion instanceof Assertion) {
                throw new InvalidArgumentException(
                    "Shadow assertions resolver for [{$agentClass}] returned a non-Assertion at index {$i}."
                );
            }

            $normalized[] = $assertion;
            $i++;
        }

        return $normalized;
    }
}

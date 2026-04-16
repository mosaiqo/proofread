<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Runner;

use Closure;
use Illuminate\Container\Container;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Agent;

final class SubjectResolver
{
    public function resolve(mixed $subject): Closure
    {
        if ($subject instanceof Closure) {
            return $subject;
        }

        if ($subject instanceof Agent) {
            return $this->wrapAgent($subject);
        }

        if (is_string($subject)) {
            return $this->resolveString($subject);
        }

        if (is_callable($subject)) {
            return Closure::fromCallable($subject);
        }

        throw new InvalidArgumentException(sprintf(
            'Subject must be a callable, an %s instance, or a class-string of an %s; got %s.',
            Agent::class,
            Agent::class,
            get_debug_type($subject),
        ));
    }

    private function resolveString(string $subject): Closure
    {
        if (is_callable($subject)) {
            return Closure::fromCallable($subject);
        }

        if (! class_exists($subject)) {
            throw new InvalidArgumentException(sprintf(
                'Subject string "%s" is neither a callable nor an existing class.',
                $subject,
            ));
        }

        if (! is_a($subject, Agent::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Subject class "%s" must implement %s.',
                $subject,
                Agent::class,
            ));
        }

        return function (mixed $input, array $case) use ($subject): string {
            unset($case);

            /** @var Agent $agent */
            $agent = Container::getInstance()->make($subject);

            return $this->invokeAgent($agent, $input);
        };
    }

    private function wrapAgent(Agent $agent): Closure
    {
        return function (mixed $input, array $case) use ($agent): string {
            unset($case);

            return $this->invokeAgent($agent, $input);
        };
    }

    private function invokeAgent(Agent $agent, mixed $input): string
    {
        $prompt = is_string($input) ? $input : (string) json_encode($input);

        return $agent->prompt($prompt)->text;
    }
}

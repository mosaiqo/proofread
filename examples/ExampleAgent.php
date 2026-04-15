<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Examples;

/**
 * Minimal classifier agent used by the example dataset.
 *
 * TODO: Wire this up against the real `laravel/ai` API once the Agent/Prism
 *       surface stabilises. Keeping it framework-free for now so the scaffold
 *       stays installable without pinning an unreleased SDK shape.
 */
class ExampleAgent
{
    /**
     * @var list<string>
     */
    public const LABELS = ['billing', 'technical', 'account', 'other'];

    public function classify(string $input): string
    {
        $text = strtolower($input);

        return match (true) {
            str_contains($text, 'charge') || str_contains($text, 'invoice') || str_contains($text, 'refund') => 'billing',
            str_contains($text, 'error') || str_contains($text, 'bug') || str_contains($text, 'broken') => 'technical',
            str_contains($text, 'password') || str_contains($text, 'login') || str_contains($text, 'account') => 'account',
            default => 'other',
        };
    }
}

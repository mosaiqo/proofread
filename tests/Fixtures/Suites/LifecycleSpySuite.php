<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Tests\Fixtures\Suites;

use Mosaiqo\Proofread\Contracts\Assertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\AssertionResult;
use Mosaiqo\Proofread\Support\Dataset;
use Throwable;

final class LifecycleSpySuite extends EvalSuite
{
    /** @var list<string> */
    public array $calls = [];

    public ?Throwable $subjectThrows = null;

    public ?Throwable $assertionThrows = null;

    public ?Throwable $setUpThrows = null;

    public ?Throwable $tearDownThrows = null;

    public function setUp(): void
    {
        $this->calls[] = 'setUp';
        if ($this->setUpThrows !== null) {
            throw $this->setUpThrows;
        }
    }

    public function tearDown(): void
    {
        $this->calls[] = 'tearDown';
        if ($this->tearDownThrows !== null) {
            throw $this->tearDownThrows;
        }
    }

    public function name(): string
    {
        return 'lifecycle-spy';
    }

    public function dataset(): Dataset
    {
        $this->calls[] = 'dataset';

        return Dataset::make('spy', [['input' => 'foo']]);
    }

    public function subject(): mixed
    {
        $this->calls[] = 'subject';

        if ($this->subjectThrows !== null) {
            $error = $this->subjectThrows;

            return static function () use ($error): never {
                throw $error;
            };
        }

        return static fn (mixed $input): string => (string) $input;
    }

    /**
     * @return array<int, Assertion>
     */
    public function assertions(): array
    {
        $this->calls[] = 'assertions';

        if ($this->assertionThrows !== null) {
            $error = $this->assertionThrows;

            return [new class($error) implements Assertion
            {
                public function __construct(private readonly Throwable $error) {}

                public function run(mixed $output, array $context = []): AssertionResult
                {
                    throw $this->error;
                }

                public function name(): string
                {
                    return 'exploding';
                }
            }];
        }

        return [];
    }
}

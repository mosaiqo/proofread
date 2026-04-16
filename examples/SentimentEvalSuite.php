<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Examples;

use Mosaiqo\Proofread\Assertions\LengthAssertion;
use Mosaiqo\Proofread\Assertions\RegexAssertion;
use Mosaiqo\Proofread\Suite\EvalSuite;
use Mosaiqo\Proofread\Support\Dataset;

final class SentimentEvalSuite extends EvalSuite
{
    public function dataset(): Dataset
    {
        /** @var Dataset $dataset */
        $dataset = require __DIR__.'/example-dataset.php';

        return $dataset;
    }

    public function subject(): mixed
    {
        return ExampleAgent::class;
    }

    public function assertions(): array
    {
        return [
            RegexAssertion::make('/^(positive|negative|neutral)$/'),
            LengthAssertion::max(20),
        ];
    }
}

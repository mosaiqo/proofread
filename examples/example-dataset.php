<?php

declare(strict_types=1);

use Mosaiqo\Proofread\Support\Dataset;

return Dataset::make('sentiment-classification', [
    ['input' => 'I love this product!', 'expected' => 'positive'],
    ['input' => 'This is terrible.', 'expected' => 'negative'],
    ['input' => 'It works as described.', 'expected' => 'neutral'],
]);

<?php

declare(strict_types=1);

return [
    [
        'input' => 'My card was charged twice for the same invoice.',
        'expected' => 'billing',
    ],
    [
        'input' => 'The dashboard throws a 500 error when I open it.',
        'expected' => 'technical',
    ],
    [
        'input' => 'I forgot my password and cannot log in to my account.',
        'expected' => 'account',
    ],
    [
        'input' => 'Please refund the last order, I never received the package.',
        'expected' => 'billing',
    ],
    [
        'input' => 'Do you offer annual pricing for teams?',
        'expected' => 'other',
    ],
];

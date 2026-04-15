<?php

declare(strict_types=1);

return [
    'enabled' => env('PROOFREAD_ENABLED', true),

    'judge' => [
        'default_model' => env('PROOFREAD_JUDGE_MODEL', 'claude-haiku-4-5'),
    ],

    'dashboard' => [
        'path' => env('PROOFREAD_DASHBOARD_PATH', 'evals'),
        'middleware' => ['web'],
    ],
];

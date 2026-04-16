<?php

declare(strict_types=1);

return [
    'enabled' => env('PROOFREAD_ENABLED', true),

    'judge' => [
        'default_model' => env('PROOFREAD_JUDGE_MODEL', 'claude-haiku-4-5'),
        'max_retries' => (int) env('PROOFREAD_JUDGE_MAX_RETRIES', 1),
    ],

    'similarity' => [
        'default_model' => env('PROOFREAD_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    'snapshots' => [
        'path' => env('PROOFREAD_SNAPSHOTS_PATH', base_path('tests/Snapshots/proofread')),
        'update' => env('PROOFREAD_UPDATE_SNAPSHOTS', false),
    ],

    'dashboard' => [
        'enabled' => env('PROOFREAD_DASHBOARD_ENABLED', true),
        'path' => env('PROOFREAD_DASHBOARD_PATH', 'evals'),
        'middleware' => ['web', 'proofread.gate'],
        'theme' => [
            // Placeholder for v1.5 custom branding.
        ],
    ],
];

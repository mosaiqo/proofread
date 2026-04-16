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

    'pricing' => [
        /*
        |--------------------------------------------------------------------------
        | Token pricing per million input/output tokens, in USD.
        |--------------------------------------------------------------------------
        |
        | These defaults are approximate snapshots intended to give Proofread a
        | working cost signal out of the box. Model providers update their
        | pricing regularly, so the numbers below can and will drift from the
        | latest published rates.
        |
        | Override this array in your application config (or merge additional
        | entries) to pin accurate pricing for the models you actually use.
        | Any model absent from this table will simply report a null cost,
        | leaving CostLimit assertions to fail-closed on missing data.
        |
        */
        'models' => [
            // Anthropic Claude family - approximate pricing, verify against
            // current Anthropic pricing before relying on these numbers.
            'claude-opus-4-6' => ['input_per_1m' => 15.00, 'output_per_1m' => 75.00],
            'claude-opus-4-5' => ['input_per_1m' => 15.00, 'output_per_1m' => 75.00],
            'claude-opus-4-1' => ['input_per_1m' => 15.00, 'output_per_1m' => 75.00],
            'claude-sonnet-4-6' => ['input_per_1m' => 3.00, 'output_per_1m' => 15.00],
            'claude-sonnet-4-5' => ['input_per_1m' => 3.00, 'output_per_1m' => 15.00],
            'claude-haiku-4-5' => ['input_per_1m' => 1.00, 'output_per_1m' => 5.00],

            // OpenAI - approximate pricing.
            'gpt-4o' => ['input_per_1m' => 2.50, 'output_per_1m' => 10.00],
            'gpt-4o-mini' => ['input_per_1m' => 0.15, 'output_per_1m' => 0.60],
            'o1-preview' => ['input_per_1m' => 15.00, 'output_per_1m' => 60.00],
            'o1-mini' => ['input_per_1m' => 3.00, 'output_per_1m' => 12.00],

            // Google Gemini - approximate pricing.
            'gemini-1.5-pro' => ['input_per_1m' => 1.25, 'output_per_1m' => 5.00],
            'gemini-1.5-flash' => ['input_per_1m' => 0.075, 'output_per_1m' => 0.30],

            // Embedding models (input only - output is 0).
            'text-embedding-3-small' => ['input_per_1m' => 0.02, 'output_per_1m' => 0.00],
            'text-embedding-3-large' => ['input_per_1m' => 0.13, 'output_per_1m' => 0.00],
        ],
    ],

    'snapshots' => [
        'path' => env('PROOFREAD_SNAPSHOTS_PATH', base_path('tests/Snapshots/proofread')),
        'update' => env('PROOFREAD_UPDATE_SNAPSHOTS', false),
    ],

    'shadow' => [
        'enabled' => env('PROOFREAD_SHADOW_ENABLED', false),
        'sample_rate' => (float) env('PROOFREAD_SHADOW_SAMPLE_RATE', 0.1),
        'agents' => [
            // Per-agent overrides, e.g.:
            // BackendDevAgent::class => ['sample_rate' => 0.05],
        ],
        'sanitize' => [
            'pii_keys' => ['email', 'phone', 'ssn', 'credit_card', 'password', 'api_key', 'token'],
            'redact_patterns' => [
                '/\b(?:\d[ -]*?){13,19}\b/' => '[CARD]',
                '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}\b/' => '[EMAIL]',
            ],
            'max_input_length' => 2000,
            'max_output_length' => 5000,
            'redacted_placeholder' => '[REDACTED]',
        ],
        'queue' => env('PROOFREAD_SHADOW_QUEUE', 'default'),

        'alerts' => [
            'enabled' => env('PROOFREAD_SHADOW_ALERTS_ENABLED', false),
            'pass_rate_threshold' => (float) env('PROOFREAD_SHADOW_ALERT_THRESHOLD', 0.85),
            'window' => env('PROOFREAD_SHADOW_ALERT_WINDOW', '1h'),
            'min_sample_size' => (int) env('PROOFREAD_SHADOW_ALERT_MIN_SAMPLES', 10),
            'dedup_window' => env('PROOFREAD_SHADOW_ALERT_DEDUP', '1h'),
            'channels' => ['mail'],
            'mail' => [
                'to' => env('PROOFREAD_ALERT_MAIL_TO'),
            ],
            'slack' => [
                'webhook_url' => env('PROOFREAD_ALERT_SLACK_WEBHOOK'),
            ],
        ],
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

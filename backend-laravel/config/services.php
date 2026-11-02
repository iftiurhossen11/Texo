<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'rag' => [
        'url' => env('RAG_SERVICE_URL', 'http://127.0.0.1:8001'),
        'api_token' => env('RAG_API_TOKEN'),
    ],

    'owner_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('TEXO_OWNER_EMAILS', '')),
    ))),
    'owner_setup_code' => env('TEXO_OWNER_SETUP_CODE'),

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'groq'),
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'groq_api_key' => env('GROQ_API_KEY'),
        'claude_model' => env('CLAUDE_MODEL', 'claude-sonnet-4-20250514'),
        'groq_model' => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
    ],

];

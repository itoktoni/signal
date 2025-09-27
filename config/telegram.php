<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your Telegram bot credentials for sending notifications
    | when high-confidence analysis results are found.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'chat_id' => env('TELEGRAM_CHAT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure when to send notifications and what information to include.
    |
    */

    'confidence_threshold' => env('TELEGRAM_CONFIDENCE_THRESHOLD', 70),
    'include_indicators' => env('TELEGRAM_INCLUDE_INDICATORS', true),
    'include_notes' => env('TELEGRAM_INCLUDE_NOTES', true),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent spam by limiting notifications per coin per time period.
    |
    */

    'rate_limit_minutes' => env('TELEGRAM_RATE_LIMIT_MINUTES', 60),
];
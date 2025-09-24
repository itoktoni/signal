<?php

// config/crypto.php
return [
    // Currency Configuration
    'currencies' => [
        'primary' => 'USD',
        'secondary' => 'IDR',
    ],

    // Exchange Rates
    'usd_to_idr' => env('USD_TO_IDR', 16500),

    // Currency API Configuration
    'currency_api' => [
        'url' => env('CURRENCY_API_URL', 'https://api.exchangerate-api.com/v4/latest/USD'),
        'key' => env('CURRENCY_API_KEY', ''), // Optional API key for premium features
        'enabled' => env('CURRENCY_API_ENABLED', true),
        'cache_duration' => env('CURRENCY_CACHE_DURATION', 21600), // 6 hours in seconds
    ],

    // Currency Display Settings
    'currency_display' => [
        'show_both_currencies' => env('SHOW_BOTH_CURRENCIES', true),
        'usd_decimals' => env('USD_DECIMALS', 2),
        'idr_decimals' => env('IDR_DECIMALS', 0),
        'format_style' => env('CURRENCY_FORMAT_STYLE', 'USD_IDR'), // USD_IDR, IDR_USD, USD_ONLY, IDR_ONLY
        'always_show_both' => true, // Always show both USD and IDR regardless of format_style
    ],

    // Definisikan timeframe yang akan dianalisis
    'timeframes' => [
        '1h' => ['label' => 'H1', 'limit' => 300],
        '4h' => ['label' => 'H4', 'limit' => 300],
        '1d' => ['label' => '1D', 'limit' => 300],
    ],

    // Tentukan timeframe mana yang menjadi acuan utama dan konfirmasi
    'main_timeframe_label' => 'H4',
    'confirmation_timeframe_label' => 'H1',

    // Parameter untuk indikator teknikal
    'indicators' => [
        'ema_short' => 9,
        'ema_long' => 21,
        'ema_trend' => 200,
        'rsi_period' => 14,
        'macd_fast' => 12,
        'macd_slow' => 26,
        'macd_signal' => 9,
    ],

    // Pembobotan skor untuk setiap indikator
    'scoring_weights' => [
        'trend' => 3.0,     // EMA 200 Trend
        'momentum' => 1.5,  // EMA 9 vs 21
        'macd' => 2.0,
        'rsi' => 1.0,
        'volume' => 1.5,    // Indikator baru: Volume
    ],

    // Aturan untuk pengambilan keputusan final
    'decision_rules' => [
        'main_tf_buy_threshold' => 2.0,
        'confirm_tf_buy_threshold' => 1.0,
        'main_tf_sell_threshold' => -2.0,
        'confirm_tf_sell_threshold' => -1.0,
    ],

    // Binance API Configuration
    'binance_api' => [
        'base_url' => env('BINANCE_API_URL', 'https://api.binance.com'),
        'exchange_info_endpoint' => '/api/v3/exchangeInfo',
        'ticker_endpoint' => '/api/v3/ticker/price',
        'klines_endpoint' => '/api/v3/klines',
        'timeout' => env('BINANCE_API_TIMEOUT', 30),
        'retry_attempts' => env('BINANCE_API_RETRY', 3),
        'retry_delay' => env('BINANCE_API_RETRY_DELAY', 1000), // milliseconds
    ],

    // Symbol filtering options
    'symbol_filters' => [
        'quote_assets' => ['USDT', 'BTC', 'ETH', 'BUSD'], // Only include these quote assets
        'exclude_symbols' => ['UP', 'DOWN', 'BULL', 'BEAR'], // Exclude leveraged tokens
        'min_volume_24h' => env('MIN_24H_VOLUME', 100000), // Minimum 24h volume in USD
        'max_symbols' => env('MAX_SYMBOLS', 500), // Limit number of symbols to fetch
    ],
];
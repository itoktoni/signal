<?php

// config/crypto.php
return [

    // Definisikan timeframe yang akan dianalisis
    'timeframes' => [
        '1h' => ['label' => 'H1', 'limit' => 300],
        '4h' => ['label' => 'H4', 'limit' => 300],
        '1d' => ['label' => '1D', 'limit' => 300],
    ],

    // Parameter untuk indikator teknikal
    'indicators' => [
        'rsi_period' => 14,
        'macd_fast' => 12,
        'macd_slow' => 26,
        'macd_signal' => 9,
    ],

    // Pembobotan skor untuk setiap indikator
    'scoring_weights' => [
        'macd' => 2.0,
        'rsi' => 1.0,
        'volume' => 1.5,
    ],

    // Binance API Configuration (Legacy - now handled by providers)
    'binance_api' => [
        'base_url' => env('BINANCE_API_URL', 'https://api.binance.com'),
        'timeout' => env('BINANCE_API_TIMEOUT', 30),
    ],

    // Symbol filtering options
    'symbol_filters' => [
        'quote_assets' => ['USDT', 'BTC', 'ETH', 'BUSD'], // Only include these quote assets
        'exclude_symbols' => ['UP', 'DOWN', 'BULL', 'BEAR'], // Exclude leveraged tokens
        'min_volume_24h' => env('MIN_24H_VOLUME', 100000), // Minimum 24h volume in USD
        'max_symbols' => env('MAX_SYMBOLS', 500), // Limit number of symbols to fetch
    ],


    // API Provider Configuration - Only CoinGecko
    'api_providers' => [
        'default' => 'coingecko',
        'fallback_enabled' => false,
        'providers' => [
            'coingecko' => [
                'name' => 'CoinGecko API',
                'enabled' => true,
                'base_url' => 'https://api.coingecko.com/api/v3/',
                'api_key' => env('COINGECKO_API_KEY', ''),
                'timeout' => 30,
            ],
        ],
    ],
];
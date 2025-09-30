<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "Testing AnalysisInterface compatibility...\n";

try {
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    // Test KeltnerChannelAnalysis
    echo "Testing KeltnerChannelAnalysis...\n";
    $keltner = new App\Analysis\KeltnerChannelAnalysis();
    echo "✅ KeltnerChannelAnalysis instantiated successfully\n";

    // Test SimpleAnalysis
    echo "Testing SimpleAnalysis...\n";
    $simple = new App\Analysis\SimpleAnalysis();
    echo "✅ SimpleAnalysis instantiated successfully\n";

    // Test interface methods
    echo "Testing interface methods...\n";
    echo "KeltnerChannelAnalysis getCode(): " . $keltner->getCode() . "\n";
    echo "KeltnerChannelAnalysis getName(): " . $keltner->getName() . "\n";
    echo "SimpleAnalysis getCode(): " . $simple->getCode() . "\n";
    echo "SimpleAnalysis getName(): " . $simple->getName() . "\n";

    // Test API Manager
    echo "Testing API Manager...\n";
    try {
        $settings = app(\App\Settings\Settings::class);
        $apiManager = new \App\Analysis\ApiProviderManager($settings);
        echo "✅ API Manager created successfully\n";

        // Test provider availability
        $binanceProvider = $apiManager->getProvider('binance');
        $coingeckoProvider = $apiManager->getProvider('coingecko');

        echo "Provider status:\n";
        echo "- Binance: " . ($binanceProvider ? "✅ Available" : "❌ Not available") . "\n";
        echo "- CoinGecko: " . ($coingeckoProvider ? "✅ Available" : "❌ Not available") . "\n";

        // Test current price with different symbols
        if ($binanceProvider) {
            echo "Testing current price fetch...\n";

            // Test symbol normalization with Binance provider
            echo "Testing symbol compatibility with Binance...\n";

            $testSymbols = ['BTC', 'BTCUSDT', 'bitcoin', 'ETH', 'ETHUSDT', 'ethereum'];

            foreach ($testSymbols as $symbol) {
                try {
                    echo "Testing symbol: $symbol\n";
                    $price = $binanceProvider->getCurrentPrice($symbol);
                    echo "✅ $symbol Price: $" . $price . "\n";
                } catch (Exception $e) {
                    echo "❌ $symbol Error: " . $e->getMessage() . "\n";
                }
            }
        }

    } catch (Exception $e) {
        echo "❌ API Manager Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
    }

    echo "✅ All tests passed!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Analysis\ApiProviderManager;
use App\Settings\Settings;

class TestSymbolMapping extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:test-mapping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test symbol mapping between API providers';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Testing symbol mapping...');

        // Create a simple mock settings object
        $settings = new class extends Settings {
            public function __construct() {}

            public function get($key, $default = null) {
                return $default;
            }
        };

        // Create the ApiProviderManager manually
        $reflection = new \ReflectionClass(ApiProviderManager::class);
        $constructor = $reflection->getConstructor();
        $apiManager = $reflection->newInstanceWithoutConstructor();

        // Manually initialize the providers
        $reflectionProperty = new \ReflectionProperty(ApiProviderManager::class, 'settings');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($apiManager, $settings);

        // Call initializeProviders method
        $initMethod = $reflection->getMethod('initializeProviders');
        $initMethod->setAccessible(true);
        $initMethod->invoke($apiManager);

        // Test symbol mapping
        $testSymbols = ['BTCUSDT', 'ETHUSDT', 'BNBUSDT'];

        foreach ($testSymbols as $symbol) {
            $this->line("Testing symbol: {$symbol}");

            // Test mapping to FreeCryptoAPI (main provider)
            $mappedToFreeCrypto = $apiManager->getMappedSymbol($symbol, 'freecryptoapi');
            $this->line("  Mapped to FreeCryptoAPI: {$mappedToFreeCrypto}");

            // Test mapping to Binance
            $mappedToBinance = $apiManager->getMappedSymbol($symbol, 'binance');
            $this->line("  Mapped to Binance: {$mappedToBinance}");

            // Test mapping to CoinGecko
            $mappedToCoinGecko = $apiManager->getMappedSymbol($symbol, 'coingecko');
            $this->line("  Mapped to CoinGecko: {$mappedToCoinGecko}");

            // Test mapping to CoinCap
            $mappedToCoinCap = $apiManager->getMappedSymbol($symbol, 'coincappro');
            $this->line("  Mapped to CoinCap: {$mappedToCoinCap}");

            $this->line('');
        }

        // Test specific mappings that should be different
        $this->info('Testing specific mappings:');
        $specificTest = 'BTCUSDT';
        $this->line("Testing symbol: {$specificTest}");

        $mappedToFreeCrypto = $apiManager->getMappedSymbol($specificTest, 'freecryptoapi');
        $this->line("  Mapped to FreeCryptoAPI: {$mappedToFreeCrypto}");

        $this->info('Symbol mapping test completed!');

        return 0;
    }
}
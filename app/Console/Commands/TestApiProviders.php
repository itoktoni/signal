<?php

namespace App\Console\Commands;

use App\Analysis\ApiProviderManager;
use App\Settings\Settings;
use Illuminate\Console\Command;

class TestApiProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:api-providers {symbol : The symbol to test (e.g., BTCUSDT)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test API providers and fallback functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $symbol = strtoupper($this->argument('symbol'));

        $this->info("Testing API providers for symbol: {$symbol}");
        $this->newLine();

        try {
            // Create a simple settings instance for testing
            $settings = new Settings(new \App\Settings\Drivers\MemoryDriver([]));
            $apiManager = new ApiProviderManager($settings);

            // Test current price
            $this->info('Testing current price retrieval...');
            $price = $apiManager->getCurrentPrice($symbol);
            $this->line("✅ Current price: \${$price}");

            // Test historical data
            $this->info('Testing historical data retrieval...');
            $historicalData = $apiManager->getHistoricalData($symbol, '1h', 5);
            $this->line("✅ Retrieved " . count($historicalData) . " historical data points");

            // Show available providers with detailed info
            $this->info('Available providers:');
            $providers = $apiManager->getAvailableProviders();
            foreach ($providers as $code => $provider) {
                $available = $apiManager->isProviderAvailable($code) ? '✅' : '❌';
                $priority = $provider->getPriority();
                $rateLimit = $provider->getRateLimitInfo();

                $this->line("  {$available} {$code}: {$provider->getName()}");
                $this->line("    Priority: {$priority}");
                $this->line("    Rate Limit: {$rateLimit['requests_remaining']}/{$rateLimit['requests_per_minute']}");

                // Show data format info
                $dataFormat = $provider->getDataFormat();
                $this->line("    Historical Format: " . ($dataFormat['historical']['type'] ?? 'Unknown'));
                $this->line("    Ticker Format: " . ($dataFormat['ticker']['type'] ?? 'Unknown'));
            }

            // Show rate limit info
            $this->info('Rate limit information:');
            $rateLimitInfo = $apiManager->getAllRateLimitInfo();
            foreach ($rateLimitInfo as $code => $info) {
                $this->line("  {$code}: {$info['requests_remaining']}/{$info['requests_per_minute']} requests remaining");
            }

        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('✅ API provider test completed successfully!');

        return 0;
    }
}
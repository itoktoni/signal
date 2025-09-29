<?php

namespace App\Console\Commands;

use App\Analysis\ApiProviderManager;
use Illuminate\Console\Command;
use App\Settings\Settings;

class TestCoinpaprikaProvider extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:test-coinpaprika {symbol?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the CoinPaprika API provider';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $symbol = $this->argument('symbol') ?? 'BTCUSDT';

        $this->info("Testing CoinPaprika API provider for symbol: {$symbol}");

        try {
            // Create a simple mock settings object
            $settings = new class extends Settings {
                public function __construct() {}

                public function get($key, $default = null) {
                    return $default;
                }
            };

            // Create the ApiProviderManager manually
            $providerManager = new ApiProviderManager($settings);

            // Test ticker data
            $this->info("Testing ticker data...");
            $tickerData = $providerManager->getTickerData($symbol);
            $this->info("Ticker data: " . json_encode($tickerData, JSON_PRETTY_PRINT));

            // Test historical data
            $this->info("Testing historical data...");
            $historicalData = $providerManager->getHistoricalData($symbol, '1h', 10);
            $this->info("Historical data points count: " . count($historicalData));

            if (!empty($historicalData)) {
                $this->info("First data point: " . json_encode($historicalData[0], JSON_PRETTY_PRINT));
            }

            $this->info("CoinPaprika API provider test completed successfully!");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error testing CoinPaprika API provider: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());

            return 1;
        }
    }
}
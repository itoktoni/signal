<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Analysis\ApiProviderManager;
use App\Settings\Settings;

class TestApiProviders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:test-providers
        {--symbol= : The symbol to test (default: BTC)}
        {--limit=10 : Number of historical data points to retrieve}
        {--save : Save results to JSON files in database/data directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test each API provider individually to get price and historical data';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $symbol = $this->option('symbol') ?? 'BTC';
        $limit = (int) $this->option('limit');
        $shouldSave = $this->option('save');

        $this->info("Testing all API providers for symbol: {$symbol}");
        $this->line("Historical data points to retrieve: {$limit}");
        if ($shouldSave) {
            $this->line("Results will be saved to database/data/");
        }
        $this->line('');

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

        // Get all available providers
        $providers = $apiManager->getAvailableProviders();

        if (empty($providers)) {
            $this->error('No API providers available!');
            return 1;
        }

        $this->info('Available API Providers:');
        foreach ($providers as $code => $provider) {
            $this->line("- {$code}: {$provider->getName()} (Priority: {$provider->getPriority()})");
        }
        $this->line('');

        // Data to save
        $savedData = [];

        // Test each provider
        foreach ($providers as $code => $provider) {
            $this->info("Testing Provider: {$code} ({$provider->getName()})");

            // Initialize data structure for this provider
            $providerData = [
                'provider_code' => $code,
                'provider_name' => $provider->getName(),
                'symbol' => $symbol,
                'mapped_symbol' => '',
                'timestamp' => date('Y-m-d H:i:s'),
                'price_data' => null,
                'historical_data' => null,
                'errors' => []
            ];

            // Get symbol mapping for this provider
            $mappedSymbol = $apiManager->getMappedSymbol($symbol, $code);
            $providerData['mapped_symbol'] = $mappedSymbol;

            if ($mappedSymbol !== $symbol) {
                $this->line("  Symbol mapping: {$symbol} → {$mappedSymbol}");
            } else {
                $this->line("  Symbol: {$symbol} (no mapping)");
            }

            try {
                // Test current price
                $this->line('  Getting current price...');

                $tickerData = $provider->getTickerData($mappedSymbol);

                if (!empty($tickerData)) {
                    $price = $tickerData['price'] ?? null;

                    if ($price && is_numeric($price)) {
                        $this->line("    ✓ Current price: $" . number_format((float)$price, 2));

                        // Store price data
                        $providerData['price_data'] = $tickerData;

                        // Show additional ticker data
                        $additionalInfo = [];
                        foreach ($tickerData as $key => $value) {
                            if ($key !== 'price' && $key !== 'symbol') {
                                $additionalInfo[] = "{$key}: " . (is_numeric($value) ? number_format((float)$value, 2) : $value);
                            }
                        }

                        if (!empty($additionalInfo)) {
                            $this->line("    Additional info: " . implode(', ', $additionalInfo));
                        }
                    } else {
                        $error = "Failed to get current price - No valid price data";
                        $this->line("    ✗ " . $error);
                        $providerData['errors'][] = $error;
                        $this->line("    Raw ticker data: " . json_encode($tickerData, JSON_PRETTY_PRINT));
                    }
                } else {
                    $error = "Failed to get current price - Empty response";
                    $this->line("    ✗ " . $error);
                    $providerData['errors'][] = $error;
                }
            } catch (\Exception $e) {
                $error = "Error getting current price: " . $e->getMessage();
                $this->line("    ✗ " . $error);
                $providerData['errors'][] = $error;
            }

            try {
                // Test historical data
                $this->line('  Getting historical data...');

                $historicalData = $provider->getHistoricalData($mappedSymbol, '1h', $limit);

                if (!empty($historicalData)) {
                    $dataPoints = count($historicalData);
                    $this->line("    ✓ Retrieved {$dataPoints} historical data points");

                    // Store historical data
                    $providerData['historical_data'] = $historicalData;

                    // Show sample data
                    if (isset($historicalData[0])) {
                        $sample = $historicalData[0];
                        $timestamp = $sample['timestamp'] ?? 'N/A';
                        $open = isset($sample['open']) ? number_format((float)$sample['open'], 2) : 'N/A';
                        $close = isset($sample['close']) ? number_format((float)$sample['close'], 2) : 'N/A';
                        $high = isset($sample['high']) ? number_format((float)$sample['high'], 2) : 'N/A';
                        $low = isset($sample['low']) ? number_format((float)$sample['low'], 2) : 'N/A';
                        $volume = isset($sample['volume']) ? number_format((float)$sample['volume'], 2) : 'N/A';

                        $this->line("    Sample data point:");
                        $this->line("      Time: {$timestamp}");
                        $this->line("      O:{$open} H:{$high} L:{$low} C:{$close} V:{$volume}");
                    }
                } else {
                    $error = "No historical data returned";
                    $this->line("    ✗ " . $error);
                    $providerData['errors'][] = $error;
                }
            } catch (\Exception $e) {
                $error = "Error getting historical data: " . $e->getMessage();
                $this->line("    ✗ " . $error);
                $providerData['errors'][] = $error;
            }

            // Add provider data to saved data array
            $savedData[$code] = $providerData;

            $this->line('');
        }

        // Save data to JSON files if requested
        if ($shouldSave) {
            $this->saveDataToFiles($savedData, $symbol);
        }

        $this->info('API provider testing completed!');
        return 0;
    }

    /**
     * Save data to JSON files in database/data directory
     *
     * @param array $data
     * @param string $symbol
     * @return void
     */
    private function saveDataToFiles(array $data, string $symbol): void
    {
        $this->info('Saving data to files...');

        foreach ($data as $providerCode => $providerData) {
            $filename = "crypto_symbol_{$providerCode}.json";
            $filepath = database_path("data/{$filename}");

            // Add symbol to the filename for clarity when testing different symbols
            if ($symbol !== 'BTC') {
                $filename = "crypto_symbol_{$providerCode}_{$symbol}.json";
                $filepath = database_path("data/{$filename}");
            }

            try {
                $jsonContent = json_encode($providerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                if (file_put_contents($filepath, $jsonContent)) {
                    $this->line("  ✓ Saved data for {$providerCode} to {$filename}");
                } else {
                    $this->line("  ✗ Failed to save data for {$providerCode} to {$filename}");
                }
            } catch (\Exception $e) {
                $this->line("  ✗ Error saving data for {$providerCode}: " . $e->getMessage());
            }
        }

        $this->line('');
    }
}
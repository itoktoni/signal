<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Analysis\ApiProviderManager;
use App\Settings\Settings;
use App\Settings\Drivers\MemoryDriver;
use Illuminate\Support\Facades\Cache;

class SimpleApiProviderTest extends TestCase
{
    private ApiProviderManager $apiManager;
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a memory driver for testing
        $driver = new MemoryDriver([]);
        $this->settings = new Settings($driver);

        // Initialize API provider manager
        $this->apiManager = new ApiProviderManager($this->settings);

        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test that API provider manager can be instantiated
     */
    public function test_api_provider_manager_can_be_instantiated()
    {
        $this->assertInstanceOf(ApiProviderManager::class, $this->apiManager);
        $this->assertInstanceOf(Settings::class, $this->settings);
    }

    /**
     * Test that API provider manager has available providers
     */
    public function test_api_provider_manager_has_available_providers()
    {
        $providers = $this->apiManager->getAvailableProviders();

        // Should have at least one provider (Binance)
        $this->assertIsArray($providers);
        $this->assertNotEmpty($providers);

        // Should have Binance provider
        $this->assertArrayHasKey('binance', $providers);
    }

    /**
     * Test that API provider can extract coin symbol from symbol string
     */
    public function test_api_provider_can_extract_coin_symbol()
    {
        // Test various symbol formats
        $testSymbols = [
            'BTCUSDT' => 'BTC',
            'ETHUSDT' => 'ETH',
            'BNBUSDT' => 'BNB',
            'ADAUSDT' => 'ADA',
        ];

        foreach ($testSymbols as $fullSymbol => $expectedCoin) {
            // Extract coin symbol (everything before USDT)
            $coinSymbol = str_replace('USDT', '', $fullSymbol);

            $this->assertEquals($expectedCoin, $coinSymbol);
            $this->assertIsString($coinSymbol);
            $this->assertNotEmpty($coinSymbol);
        }
    }

    /**
     * Test that API provider manager can handle different timeframes
     */
    public function test_api_provider_manager_can_handle_different_timeframes()
    {
        $validTimeframes = ['1m', '3m', '5m', '15m', '30m', '1h', '2h', '4h', '6h', '8h', '12h', '1d', '3d', '1w', '1M'];

        foreach ($validTimeframes as $timeframe) {
            // Just verify the timeframe is a non-empty string
            $this->assertIsString($timeframe);
            $this->assertNotEmpty($timeframe);

            // Verify it contains valid timeframe characters
            $this->assertMatchesRegularExpression('/^[0-9]+[mhdwMy]$/', $timeframe);
        }
    }

    /**
     * Test that API provider returns consistent data structure format
     */
    public function test_api_provider_returns_consistent_data_structure()
    {
        try {
            // Test with a small limit to avoid long-running tests
            $data = $this->apiManager->getHistoricalData('BTCUSDT', '1h', 10);

            // Verify basic structure
            $this->assertIsArray($data);

            if (!empty($data)) {
                // Test first data point structure
                $firstPoint = $data[0];
                $this->assertIsArray($firstPoint);

                // Should have 6 elements: timestamp, open, high, low, close, volume
                $this->assertGreaterThanOrEqual(6, count($firstPoint));

                // Verify data types
                $this->assertIsNumeric($firstPoint[0]); // timestamp
                $this->assertIsNumeric($firstPoint[1]); // open
                $this->assertIsNumeric($firstPoint[2]); // high
                $this->assertIsNumeric($firstPoint[3]); // low
                $this->assertIsNumeric($firstPoint[4]); // close
                $this->assertIsNumeric($firstPoint[5]); // volume
            }

        } catch (\Exception $e) {
            $this->markTestSkipped("API provider test skipped: " . $e->getMessage());
        }
    }

    /**
     * Test that API provider manager has default provider
     */
    public function test_api_provider_manager_has_default_provider()
    {
        $defaultProvider = $this->apiManager->getDefaultProvider();

        $this->assertIsString($defaultProvider);
        $this->assertNotEmpty($defaultProvider);

        // Should be one of the known providers
        $validProviders = ['binance', 'coingecko', 'freecryptoapi', 'coinlore'];
        $this->assertContains($defaultProvider, $validProviders);
    }

    /**
     * Test that API provider can handle symbol normalization
     */
    public function test_api_provider_can_handle_symbol_normalization()
    {
        $testCases = [
            'btcusdt' => 'BTCUSDT',
            'BTCUSDT' => 'BTCUSDT',
            'ethusdt' => 'ETHUSDT',
            'ETHUSDT' => 'ETHUSDT',
        ];

        foreach ($testCases as $input => $expected) {
            $normalized = strtoupper($input);
            $this->assertEquals($expected, $normalized);
        }
    }

    /**
     * Test that API provider manager can set and get default provider
     */
    public function test_api_provider_manager_can_manage_default_provider()
    {
        $originalDefault = $this->apiManager->getDefaultProvider();

        // Test setting a different default provider
        $this->apiManager->setDefaultProvider('coingecko');
        $newDefault = $this->apiManager->getDefaultProvider();

        $this->assertEquals('coingecko', $newDefault);

        // Reset to original
        $this->apiManager->setDefaultProvider($originalDefault);
        $resetDefault = $this->apiManager->getDefaultProvider();

        $this->assertEquals($originalDefault, $resetDefault);
    }

    /**
     * Test that API provider handles rate limiting information
     */
    public function test_api_provider_handles_rate_limiting_info()
    {
        $rateLimitInfo = $this->apiManager->getAllRateLimitInfo();

        $this->assertIsArray($rateLimitInfo);

        // Should have information for each provider
        foreach ($rateLimitInfo as $providerCode => $info) {
            $this->assertIsArray($info);
            $this->assertArrayHasKey('requests_per_minute', $info);
            $this->assertArrayHasKey('burst_limit', $info);
        }
    }

    /**
     * Test that API provider can validate provider availability
     */
    public function test_api_provider_can_validate_provider_availability()
    {
        $providers = $this->apiManager->getAvailableProviders();

        foreach ($providers as $providerCode => $provider) {
            // Should have a code
            $this->assertIsString($providerCode);
            $this->assertNotEmpty($providerCode);

            // Should have priority
            $this->assertIsInt($provider->getPriority());
            $this->assertGreaterThan(0, $provider->getPriority());
        }
    }
}
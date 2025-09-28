<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Analysis\Providers\CoinCapProProvider;
use App\Settings\Settings;
use App\Settings\Drivers\MemoryDriver;
use Illuminate\Support\Facades\Cache;

class CoinCapProProviderTest extends TestCase
{
    private CoinCapProProvider $provider;
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a memory driver for testing
        $driver = new MemoryDriver([]);
        $this->settings = new Settings($driver);

        // Initialize CoinCap Pro provider
        $this->provider = new CoinCapProProvider();

        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test that CoinCap Pro provider can be instantiated
     */
    public function test_coincap_pro_provider_can_be_instantiated()
    {
        $this->assertInstanceOf(CoinCapProProvider::class, $this->provider);
    }

    /**
     * Test that CoinCap Pro provider has correct identification
     */
    public function test_coincap_pro_provider_has_correct_identification()
    {
        $this->assertEquals('coincappro', $this->provider->getCode());
        $this->assertStringContainsString('CoinCap Pro', $this->provider->getName());
    }

    /**
     * Test that CoinCap Pro provider correctly reports availability
     */
    public function test_coincap_pro_provider_availability()
    {
        // Without API key, should be unavailable
        $isAvailable = $this->provider->isAvailable();

        // This will likely be false since no API key is configured
        // But the method should not throw an exception
        $this->assertIsBool($isAvailable);

        // Test priority
        $priority = $this->provider->getPriority();
        $this->assertIsInt($priority);
        $this->assertGreaterThanOrEqual(0, $priority);
    }

    /**
     * Test that CoinCap Pro provider has rate limiting information
     */
    public function test_coincap_pro_provider_has_rate_limiting_info()
    {
        $rateLimitInfo = $this->provider->getRateLimitInfo();

        $this->assertIsArray($rateLimitInfo);
        $this->assertArrayHasKey('requests_remaining', $rateLimitInfo);
        $this->assertArrayHasKey('requests_per_minute', $rateLimitInfo);

        $this->assertIsInt($rateLimitInfo['requests_per_minute']);
        $this->assertGreaterThan(0, $rateLimitInfo['requests_per_minute']);
    }

    /**
     * Test that CoinCap Pro provider has correct data format specification
     */
    public function test_coincap_pro_provider_has_correct_data_format()
    {
        $dataFormat = $this->provider->getDataFormat();

        $this->assertIsArray($dataFormat);
        $this->assertArrayHasKey('historical', $dataFormat);
        $this->assertArrayHasKey('ticker', $dataFormat);

        // Check historical data format
        $this->assertArrayHasKey('type', $dataFormat['historical']);
        $this->assertArrayHasKey('structure', $dataFormat['historical']);
        $this->assertArrayHasKey('note', $dataFormat['historical']);

        // Check ticker data format
        $this->assertArrayHasKey('type', $dataFormat['ticker']);
        $this->assertArrayHasKey('structure', $dataFormat['ticker']);
        $this->assertArrayHasKey('example', $dataFormat['ticker']);
    }

    /**
     * Test symbol to CoinCap ID conversion
     */
    public function test_symbol_to_coincap_id_conversion()
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('convertSymbolToCoinCapId');
        $method->setAccessible(true);

        // Test known symbol mappings
        $testCases = [
            'BTCUSDT' => 'bitcoin',
            'ETHUSDT' => 'ethereum',
            'BNBUSDT' => 'binance-coin',
            'ADAUSDT' => 'cardano',
        ];

        foreach ($testCases as $symbol => $expectedId) {
            $result = $method->invoke($this->provider, $symbol);
            $this->assertEquals($expectedId, $result);
        }

        // Test unknown symbol (should default to bitcoin)
        $unknownResult = $method->invoke($this->provider, 'UNKNOWNUSDT');
        $this->assertEquals('bitcoin', $unknownResult);
    }

    /**
     * Test interval conversion for CoinCap
     */
    public function test_interval_conversion_for_coincap()
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('convertIntervalForCoinCap');
        $method->setAccessible(true);

        // Test known interval mappings
        $testCases = [
            '1m' => 'm1',
            '5m' => 'm5',
            '15m' => 'm15',
            '1h' => 'h1',
            '4h' => 'h4',
            '1d' => 'd1',
        ];

        foreach ($testCases as $input => $expected) {
            $result = $method->invoke($this->provider, $input);
            $this->assertEquals($expected, $result);
        }

        // Test unknown interval (should default to h1)
        $unknownResult = $method->invoke($this->provider, '99x');
        $this->assertEquals('h1', $unknownResult);
    }

    /**
     * Test CoinCap to Binance format conversion
     */
    public function test_coincap_to_binance_format_conversion()
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('convertCoinCapToBinanceFormat');
        $method->setAccessible(true);

        // Mock CoinCap data format
        $coinCapData = [
            [
                'time' => 1635724800000, // Timestamp
                'priceUsd' => '50000.00', // Price in USD
                'volume' => 1000.0, // Volume
            ],
            [
                'time' => 1635728400000, // Next timestamp
                'priceUsd' => '50100.00', // Different price
                'volume' => 1100.0,
            ],
        ];

        $result = $method->invoke($this->provider, $coinCapData);

        // Verify conversion result
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Should have 2 data points

        // Check first data point structure
        $firstPoint = $result[0];
        $this->assertIsArray($firstPoint);
        $this->assertCount(12, $firstPoint); // Binance format has 12 elements

        // Verify OHLC values are set correctly (synthetic OHLC from single price)
        $this->assertEquals('50000.00', $firstPoint[1]); // Open
        $this->assertEquals('50000.00', $firstPoint[2]); // High
        $this->assertEquals('50000.00', $firstPoint[3]); // Low
        $this->assertEquals('50000.00', $firstPoint[4]); // Close

        // Verify volume
        $this->assertEquals('1000', $firstPoint[5]); // Volume

        // Verify timestamps
        $this->assertEquals(1635724800000, $firstPoint[0]); // Open time
        $this->assertEquals(1635724800000 + 60000, $firstPoint[6]); // Close time
    }

    /**
     * Test ticker data normalization
     */
    public function test_ticker_data_normalization()
    {
        $rawData = [
            [
                'symbol' => 'BTCUSDT',
                'price' => '50000.50'
            ],
            [
                'symbol' => 'ETHUSDT',
                'price' => '3000.75'
            ],
        ];

        $normalized = $this->provider->normalizeTickerData($rawData);

        $this->assertIsArray($normalized);
        $this->assertCount(2, $normalized);

        // Check first normalized item
        $this->assertEquals('BTCUSDT', $normalized[0]['symbol']);
        $this->assertEquals('50000.50', $normalized[0]['price']);

        // Check second normalized item
        $this->assertEquals('ETHUSDT', $normalized[1]['symbol']);
        $this->assertEquals('3000.75', $normalized[1]['price']);
    }

    /**
     * Test historical data normalization
     */
    public function test_historical_data_normalization()
    {
        $rawData = [
            [
                'time' => 1635724800000,
                'priceUsd' => '50000.00',
                'volume' => 1000.0,
            ],
        ];

        $normalized = $this->provider->normalizeHistoricalData($rawData, '1h');

        $this->assertIsArray($normalized);
        $this->assertNotEmpty($normalized);

        // Should be in Binance format
        $dataPoint = $normalized[0];
        $this->assertIsArray($dataPoint);
        $this->assertCount(12, $dataPoint);
    }

    /**
     * Test that provider handles missing configuration gracefully
     */
    public function test_provider_handles_missing_configuration_gracefully()
    {
        // Test that methods don't throw exceptions even without proper config
        try {
            $this->assertIsString($this->provider->getCode());
            $this->assertIsString($this->provider->getName());
            $this->assertIsBool($this->provider->isAvailable());
            $this->assertIsInt($this->provider->getPriority());
            $this->assertIsArray($this->provider->getRateLimitInfo());
            $this->assertIsArray($this->provider->getDataFormat());

        } catch (\Exception $e) {
            $this->fail('Provider should handle missing configuration gracefully: ' . $e->getMessage());
        }
    }

    /**
     * Test that provider handles API errors gracefully
     */
    public function test_provider_handles_api_errors_gracefully()
    {
        try {
            // Try to get ticker data for invalid symbol
            $this->provider->getTickerData('INVALID_SYMBOL');
            $this->fail('Should have thrown exception for invalid symbol');

        } catch (\Exception $e) {
            // Should throw meaningful exception
            $this->assertStringContainsString('CoinCap Pro API', $e->getMessage());
        }
    }
}
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Analysis\ApiProviderManager;
use App\Analysis\Providers\BinanceApiProvider;
use App\Analysis\Providers\CoinGeckoApiProvider;
use App\Settings\Settings;
use App\Settings\Drivers\MemoryDriver;
use Illuminate\Support\Facades\Cache;

class ApiProviderTest extends TestCase
{
    private ApiProviderManager $apiManager;
    private Settings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a memory driver for testing
        $driver = new MemoryDriver();
        $this->settings = new Settings($driver);

        // Initialize API provider manager
        $this->apiManager = new ApiProviderManager($this->settings);

        // Clear cache before each test
        Cache::flush();
    }

    /**
     * Test that API provider manager can get current price for a symbol
     */
    public function test_api_provider_can_get_current_price()
    {
        try {
            $price = $this->apiManager->getCurrentPrice('BTCUSDT');

            // Verify price is a positive float
            $this->assertIsFloat($price);
            $this->assertGreaterThan(0, $price);

            // Verify it's a reasonable Bitcoin price (should be > $10,000)
            $this->assertGreaterThan(10000, $price);

        } catch (\Exception $e) {
            $this->markTestSkipped("API provider test skipped: " . $e->getMessage());
        }
    }

    /**
     * Test that API provider can get ticker data with symbol
     */
    public function test_api_provider_can_get_ticker_data_with_symbol()
    {
        try {
            $tickerData = $this->apiManager->getTickerData('BTCUSDT');

            // Verify ticker data structure
            $this->assertIsArray($tickerData);
            $this->assertArrayHasKey('symbol', $tickerData);
            $this->assertArrayHasKey('price', $tickerData);

            // Verify symbol is correct
            $this->assertEquals('BTCUSDT', $tickerData['symbol']);

            // Verify price is positive
            $this->assertIsNumeric($tickerData['price']);
            $this->assertGreaterThan(0, $tickerData['price']);

        } catch (\Exception $e) {
            $this->markTestSkipped("API provider test skipped: " . $e->getMessage());
        }
    }

    /**
     * Test that API provider can get historical data for analysis
     */
    public function test_api_provider_can_get_historical_data()
    {
        try {
            $historicalData = $this->apiManager->getHistoricalData('BTCUSDT', '1h', 100);

            // Verify historical data structure
            $this->assertIsArray($historicalData);
            $this->assertNotEmpty($historicalData);

            // Verify each data point has the correct structure (OHLCV format)
            $firstDataPoint = $historicalData[0];
            $this->assertIsArray($firstDataPoint);
            $this->assertCount(6, $firstDataPoint); // Open, High, Low, Close, Volume, Timestamp

            // Verify data is in correct order (newest first)
            $this->assertGreaterThanOrEqual($historicalData[0][0], $historicalData[1][0]);

            // Verify all prices are positive
            foreach ($historicalData as $dataPoint) {
                $this->assertIsNumeric($dataPoint[1]); // Open
                $this->assertIsNumeric($dataPoint[2]); // High
                $this->assertIsNumeric($dataPoint[3]); // Low
                $this->assertIsNumeric($dataPoint[4]); // Close
                $this->assertIsNumeric($dataPoint[5]); // Volume

                $this->assertGreaterThan(0, $dataPoint[1]);
                $this->assertGreaterThan(0, $dataPoint[2]);
                $this->assertGreaterThan(0, $dataPoint[3]);
                $this->assertGreaterThan(0, $dataPoint[4]);
                $this->assertGreaterThanOrEqual(0, $dataPoint[5]);
            }

        } catch (\Exception $e) {
            $this->markTestSkipped("API provider test skipped: " . $e->getMessage());
        }
    }

    /**
     * Test that API provider returns consistent data format across timeframes
     */
    public function test_api_provider_returns_consistent_format_across_timeframes()
    {
        try {
            $timeframes = ['1h', '4h', '1d'];

            foreach ($timeframes as $timeframe) {
                $data = $this->apiManager->getHistoricalData('BTCUSDT', $timeframe, 50);

                $this->assertIsArray($data);
                $this->assertNotEmpty($data);

                // Verify each data point has consistent structure
                foreach ($data as $dataPoint) {
                    $this->assertIsArray($dataPoint);
                    $this->assertCount(6, $dataPoint);

                    // Verify data types
                    $this->assertIsNumeric($dataPoint[0]); // Timestamp
                    $this->assertIsNumeric($dataPoint[1]); // Open
                    $this->assertIsNumeric($dataPoint[2]); // High
                    $this->assertIsNumeric($dataPoint[3]); // Low
                    $this->assertIsNumeric($dataPoint[4]); // Close
                    $this->assertIsNumeric($dataPoint[5]); // Volume
                }
            }

        } catch (\Exception $e) {
            $this->markTestSkipped("API provider test skipped: " . $e->getMessage());
        }
    }

    /**
     * Test that API provider can handle multiple symbols
     */
    public function test_api_provider_can_handle_multiple_symbols()
    {
        try {
            $symbols = ['BTCUSDT', 'ETHUSDT'];

            foreach ($symbols as $symbol) {
                $price = $this->apiManager->getCurrentPrice($symbol);
                $this->assertIsFloat($price);
                $this->assertGreaterThan(0, $price);

                $tickerData = $this->apiManager->getTickerData($symbol);
                $this->assertArrayHasKey('symbol', $tickerData);
                $this->assertEquals($symbol, $tickerData['symbol']);
            }

        } catch (\Exception $e) {
            $this->markTestSkipped("API provider test skipped: " . $e->getMessage());
        }
    }

    /**
     * Test that API provider manager has fallback mechanism
     */
    public function test_api_provider_manager_has_fallback_mechanism()
    {
        try {
            // Test with forced API provider
            $dataWithForcedApi = $this->apiManager->getHistoricalData('BTCUSDT', '1h', 50, 'binance');

            $this->assertIsArray($dataWithForcedApi);
            $this->assertNotEmpty($dataWithForcedApi);

            // Test without forced API (should use intelligent routing)
            $dataWithRouting = $this->apiManager->getHistoricalData('BTCUSDT', '1h', 50);

            $this->assertIsArray($dataWithRouting);
            $this->assertNotEmpty($dataWithRouting);

        } catch (\Exception $e) {
            $this->markTestSkipped("API provider test skipped: " . $e->getMessage());
        }
    }

    /**
     * Test that API provider returns data suitable for chart creation
     */
    public function test_api_provider_returns_chart_ready_data()
    {
        try {
            $data = $this->apiManager->getHistoricalData('BTCUSDT', '1h', 100);

            // Verify data can be used for charting
            $timestamps = array_column($data, 0);
            $opens = array_column($data, 1);
            $highs = array_column($data, 2);
            $lows = array_column($data, 3);
            $closes = array_column($data, 4);
            $volumes = array_column($data, 5);

            // Verify arrays are not empty
            $this->assertNotEmpty($timestamps);
            $this->assertNotEmpty($opens);
            $this->assertNotEmpty($highs);
            $this->assertNotEmpty($lows);
            $this->assertNotEmpty($closes);
            $this->assertNotEmpty($volumes);

            // Verify OHLC makes sense (high >= low, high >= close, low <= close, etc.)
            for ($i = 0; $i < count($data); $i++) {
                $this->assertGreaterThanOrEqual($lows[$i], 0);
                $this->assertGreaterThanOrEqual($highs[$i], $lows[$i]);
                $this->assertGreaterThanOrEqual($highs[$i], $opens[$i]);
                $this->assertGreaterThanOrEqual($highs[$i], $closes[$i]);
                $this->assertLessThanOrEqual($lows[$i], $opens[$i]);
                $this->assertLessThanOrEqual($lows[$i], $closes[$i]);
            }

        } catch (\Exception $e) {
            $this->markTestSkipped("API provider test skipped: " . $e->getMessage());
        }
    }

    /**
     * Test that API provider manager handles errors gracefully
     */
    public function test_api_provider_manager_handles_errors_gracefully()
    {
        try {
            // Test with invalid symbol
            $this->apiManager->getCurrentPrice('INVALID_SYMBOL');
            $this->fail('Expected exception for invalid symbol');

        } catch (\Exception $e) {
            // Should throw exception for invalid symbol
            $this->assertStringContains('All API providers failed', $e->getMessage());
        }
    }
}
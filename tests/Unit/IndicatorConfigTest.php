<?php

namespace Tests\Unit;

use App\Analysis\AnalysisService;
use App\Enums\AnalysisType;
use Tests\TestCase;

class IndicatorConfigTest extends TestCase
{
    /**
     * Test that the indicator configuration is correctly provided by analysis services.
     *
     * @return void
     */
    public function test_indicator_configuration_is_provided_by_analysis_services()
    {
        // Create a mock AnalysisService (since it's abstract, we'll create an anonymous class)
        $analysisService = new class extends AnalysisService {
            public function getName(): string
            {
                return 'test';
            }

            public function analyze(string $symbol, float $amount = 1000): object
            {
                // Not needed for this test
                return (object)[];
            }

            // Make formatResult public for testing
            public function publicFormatResult(
                string $title,
                string $signal,
                float $confidence,
                float $entry,
                float $stopLoss,
                float $takeProfit,
                float $riskReward,
                float $positionSize,
                string $analystMethod = 'basic',
                array $indicators = [],
                string $orderType = 'taker',
                array $indicatorConfig = []
            ): object {
                return $this->formatResult($title, $signal, $confidence, $entry, $stopLoss, $takeProfit, $riskReward, $positionSize, $analystMethod, $indicators, $orderType, $indicatorConfig);
            }
        };

        // Test with indicator configuration
        $indicatorConfig = [
            'ema20' => ['label' => 'EMA 20', 'format' => 'price', 'class' => 'col-2'],
            'rsi' => ['label' => 'RSI 14', 'format' => 'number', 'class' => 'col-2'],
        ];

        $result = $analysisService->publicFormatResult(
            'Test Analysis',
            'BUY',
            80,
            100,
            95,
            110,
            2.0,
            1000,
            'test',
            [],
            'taker',
            $indicatorConfig
        );

        // Check that the indicator configuration is included in the result
        $this->assertArrayHasKey('indicator_config', (array) $result);
        $this->assertEquals($indicatorConfig, $result->indicator_config);
    }

    /**
     * Test that the base AnalysisService provides indicator configurations.
     *
     * @return void
     */
    public function test_base_analysis_service_provides_indicator_configurations()
    {
        // Create a mock AnalysisService (since it's abstract, we'll create an anonymous class)
        $analysisService = new class extends AnalysisService {
            public function getName(): string
            {
                return 'test';
            }

            public function analyze(string $symbol, float $amount = 1000): object
            {
                // Not needed for this test
                return (object)[];
            }

            // Make getIndicatorConfigurations public for testing
            public function publicGetIndicatorConfigurations(): array
            {
                return $this->getIndicatorConfigurations();
            }

            // Make getIndicatorConfig public for testing
            public function publicGetIndicatorConfig(string $method): array
            {
                return $this->getIndicatorConfig($method);
            }
        };

        // Test that configurations are provided
        $configurations = $analysisService->publicGetIndicatorConfigurations();

        // Check that we have configurations for sniper and dynamic_rr
        $this->assertArrayHasKey('sniper', $configurations);
        $this->assertArrayHasKey('dynamic_rr', $configurations);
        $this->assertArrayHasKey('default', $configurations);

        // Check that sniper configuration has the expected indicators
        $sniperConfig = $configurations['sniper'];
        $this->assertArrayHasKey('ema9', $sniperConfig);
        $this->assertArrayHasKey('ema21', $sniperConfig);
        $this->assertArrayHasKey('rsi', $sniperConfig);

        // Test getting specific configuration
        $sniperConfig = $analysisService->publicGetIndicatorConfig('sniper');
        $this->assertArrayHasKey('ema9', $sniperConfig);

        $dynamicRrConfig = $analysisService->publicGetIndicatorConfig('dynamic_rr');
        $this->assertArrayHasKey('atr', $dynamicRrConfig);
    }
}
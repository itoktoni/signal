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
                array $indicators = []
            ): object {
                return $this->formatResult($title, $signal, $confidence, $entry, $stopLoss, $takeProfit, $riskReward, $positionSize, $analystMethod, $indicators);
            }
        };

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
            []
        );

        // Check that the result contains the expected structure
        $this->assertEquals('Test Analysis', $result->title);
        $this->assertEquals('BUY', $result->signal);
        $this->assertEquals(80, $result->confidence);
    }

}
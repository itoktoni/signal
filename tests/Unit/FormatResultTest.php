<?php

namespace Tests\Unit;

use App\Analysis\AnalysisService;
use Tests\TestCase;

class FormatResultTest extends TestCase
{
    /**
     * Test that the formatResult method correctly passes the order type to calculateFees.
     *
     * @return void
     */
    public function test_format_result_passes_order_type_to_calculate_fees()
    {
        // Create a mock AnalysisService (since it's abstract, we'll create an anonymous class)
        $analysisService = new class extends AnalysisService {
            public function getName(): string
            {
                return 'test';
            }

            public function getDescription(): string
            {
                return 'Test analysis service';
            }

            public function getIndicators(): array
            {
                return [];
            }

            public function getNotes(): string
            {
                return 'Test notes';
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
                string $orderType = 'taker'
            ): object {
                return $this->formatResult($title, $signal, $confidence, $entry, $stopLoss, $takeProfit, $riskReward, $positionSize, $analystMethod, $indicators, $orderType);
            }
        };

        // Test with taker order type
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
            'taker'
        );

        // Check that the fee description contains "taker"
        $this->assertStringContainsString('taker', $result->fee['description']);

        // Test with maker order type
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
            'maker'
        );

        // Check that the fee description contains "maker"
        $this->assertStringContainsString('maker', $result->fee['description']);
    }
}
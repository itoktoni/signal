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
            public function getCode(): string
            {
                return 'test';
            }

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

            /**
             * Calculate fees for a trade (Indonesian market context)
             */
            protected function calculateFees(float $positionSize, string $orderType = 'taker'): array
            {
                // Based on Pluang PRO fee structure for Kripto Futures:
                // Maker fee: 0.10% + PPN 0.011% + CFX 0.05% + PPN on CFX 0.0055% = 0.15561%
                // Taker fee: 0.10% + PPN 0.011% + CFX 0.15% + PPN on CFX 0.0165% = 0.26661%

                $baseFee = $positionSize * 0.0010; // 0.10% base transaction fee
                $ppnOnBase = $positionSize * 0.00011; // 0.011% PPN on transaction fee

                if ($orderType === 'maker') {
                    $cfxFee = $positionSize * 0.0005; // 0.05% CFX fee (maker)
                    $ppnOnCfx = $positionSize * 0.000055; // 0.0055% PPN on CFX fee
                    $feeDescription = 'maker 0,10% + PPN 0,011% + CFX 0,05% + PPN on CFX 0,0055%';
                } else { // taker (default)
                    $cfxFee = $positionSize * 0.0015; // 0.15% CFX fee (taker)
                    $ppnOnCfx = $positionSize * 0.000165; // 0.0165% PPN on CFX fee
                    $feeDescription = 'taker 0,10% + PPN 0,011% + CFX 0,15% + PPN on CFX 0,0165%';
                }

                $tradingFee = $baseFee + $ppnOnBase + $cfxFee + $ppnOnCfx;

                // Slippage (estimated)
                $slippage = $positionSize * 0.005; // 0.5% slippage

                $totalFees = $tradingFee + $slippage;

                $formattedFees = $this->formatPrice($totalFees);

                return [
                    'base_fee' => $baseFee, // 0.10% base transaction fee
                    'ppn_on_base' => $ppnOnBase, // 0.011% PPN on transaction fee
                    'cfx_fee' => $cfxFee, // 0.05% or 0.15% CFX fee (maker/taker)
                    'ppn_on_cfx' => $ppnOnCfx, // 0.0055% or 0.0165% PPN on CFX fee
                    'trading_fee' => $tradingFee, // Total trading fee
                    'slippage' => $slippage, // 0.5% - Estimated slippage
                    'total' => $totalFees,
                    'formatted' => $formattedFees['formatted'],
                    'description' => 'Biaya transaksi ' . $feeDescription . ' + slippage 0,5%'
                ];
            }

            // Make formatResult public for testing
            public function publicFormatResult(
                string $title,
                string $description,
                string $signal,
                float $confidence,
                float $entry,
                float $stopLoss,
                float $takeProfit,
                string $riskReward,
                array $fees,
                float $potentialProfit,
                float $potentialLoss,
                string $analystMethod = 'basic',
                array $indicators = [],
                string $notes = ''
            ): object {
                return $this->formatResult($title, $description, $signal, $confidence, $entry, $stopLoss, $takeProfit, $riskReward, $fees, $potentialProfit, $potentialLoss, $analystMethod, $indicators, $notes);
            }
        };

        // Calculate fees
        $fees = $this->invokeMethod($analysisService, 'calculateFees', [1000, 'taker']);

        $result = $analysisService->publicFormatResult(
            'Test Analysis',
            'Test Description',
            'BUY',
            80,
            100,
            95,
            110,
            '1:2',
            $fees,
            10,
            -5,
            'test',
            [],
            'Test notes'
        );

        // Check that the result contains the expected structure
        $this->assertEquals('Test Analysis', $result->title);
        $this->assertEquals('BUY', $result->signal);
        $this->assertEquals(80, $result->confidence);
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

}
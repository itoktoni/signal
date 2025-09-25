<?php

namespace Tests\Unit;

use App\Analysis\AnalysisService;
use Tests\TestCase;

class FeeCalculationTest extends TestCase
{
    /**
     * Test that the fee calculation matches the Pluang PRO structure for Kripto Futures.
     *
     * @return void
     */
    public function test_fee_calculation_matches_pluang_pro_structure()
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
        };

        // Test with a position size of $1000
        $positionSize = 1000;

        // Test taker fees
        $takerFees = $this->invokeMethod($analysisService, 'calculateFees', [$positionSize, 'taker']);

        // Expected values based on Pluang PRO structure:
        // Base fee: 0.10% of $1000 = $1.00
        // PPN on base: 0.011% of $1000 = $0.11
        // CFX fee (taker): 0.15% of $1000 = $1.50
        // PPN on CFX (taker): 0.0165% of $1000 = $0.165
        // Slippage: 0.5% of $1000 = $5.00
        // Total: $1.00 + $0.11 + $1.50 + $0.165 + $5.00 = $7.775

        $this->assertEqualsWithDelta(1.00, $takerFees['base_fee'], 0.01);
        $this->assertEqualsWithDelta(0.11, $takerFees['ppn_on_base'], 0.01);
        $this->assertEqualsWithDelta(1.50, $takerFees['cfx_fee'], 0.01);
        $this->assertEqualsWithDelta(0.165, $takerFees['ppn_on_cfx'], 0.01);
        $this->assertEqualsWithDelta(7.775, $takerFees['total'], 0.01);

        // Test maker fees
        $makerFees = $this->invokeMethod($analysisService, 'calculateFees', [$positionSize, 'maker']);

        // Expected values based on Pluang PRO structure:
        // Base fee: 0.10% of $1000 = $1.00
        // PPN on base: 0.011% of $1000 = $0.11
        // CFX fee (maker): 0.05% of $1000 = $0.50
        // PPN on CFX (maker): 0.0055% of $1000 = $0.055
        // Slippage: 0.5% of $1000 = $5.00
        // Total: $1.00 + $0.11 + $0.50 + $0.055 + $5.00 = $6.665

        $this->assertEqualsWithDelta(1.00, $makerFees['base_fee'], 0.01);
        $this->assertEqualsWithDelta(0.11, $makerFees['ppn_on_base'], 0.01);
        $this->assertEqualsWithDelta(0.50, $makerFees['cfx_fee'], 0.01);
        $this->assertEqualsWithDelta(0.055, $makerFees['ppn_on_cfx'], 0.01);
        $this->assertEqualsWithDelta(6.665, $makerFees['total'], 0.01);
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
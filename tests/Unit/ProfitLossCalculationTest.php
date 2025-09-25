<?php

namespace Tests\Unit;

use App\Analysis\AnalysisService;
use Tests\TestCase;

class ProfitLossCalculationTest extends TestCase
{
    /**
     * Test that potential profit and loss are calculated correctly for long positions
     *
     * @return void
     */
    public function test_profit_loss_calculation_for_long_position()
    {
        // Create a mock AnalysisService
        $service = $this->getMockForAbstractClass(AnalysisService::class);

        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculatePotentialPL');
        $method->setAccessible(true);

        // Test with a long position
        $result = $method->invokeArgs($service, [
            50000,  // entryPrice
            49000,  // stopLoss
            53000,  // takeProfit
            1000,   // positionSize
            'long'  // positionType
        ]);

        // Assertions
        $this->assertArrayHasKey('potential_profit', $result);
        $this->assertArrayHasKey('potential_loss', $result);

        // For a long position with these parameters, we should have:
        // - Positive potential profit
        // - Negative potential loss
        $this->assertGreaterThan(0, $result['potential_profit']);
        $this->assertLessThan(0, $result['potential_loss']);
    }

    /**
     * Test that potential profit and loss are calculated correctly for short positions
     *
     * @return void
     */
    public function test_profit_loss_calculation_for_short_position()
    {
        // Create a mock AnalysisService
        $service = $this->getMockForAbstractClass(AnalysisService::class);

        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('calculatePotentialPL');
        $method->setAccessible(true);

        // Test with a short position
        $result = $method->invokeArgs($service, [
            50000,  // entryPrice
            51000,  // stopLoss
            47000,  // takeProfit
            1000,   // positionSize
            'short' // positionType
        ]);

        // Assertions
        $this->assertArrayHasKey('potential_profit', $result);
        $this->assertArrayHasKey('potential_loss', $result);

        // For a short position with these parameters, we should have:
        // - Positive potential profit
        // - Negative potential loss
        $this->assertGreaterThan(0, $result['potential_profit']);
        $this->assertLessThan(0, $result['potential_loss']);
    }
}
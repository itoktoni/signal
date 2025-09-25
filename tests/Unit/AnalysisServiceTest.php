<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Analysis\AnalysisServiceFactory;
use App\Analysis\SniperService;
use App\Analysis\DynamicRRService;

class AnalysisServiceTest extends TestCase
{
    /**
     * Test that the analysis service factory creates the correct services.
     */
    public function test_analysis_service_factory_creates_correct_services()
    {
        $sniperService = AnalysisServiceFactory::create('sniper');
        $this->assertInstanceOf(SniperService::class, $sniperService);

        $dynamicRRService = AnalysisServiceFactory::create('dynamic_rr');
        $this->assertInstanceOf(\App\Analysis\DynamicRRService::class, $dynamicRRService);
    }

    /**
     * Test that the analysis service factory returns available methods.
     */
    public function test_analysis_service_factory_returns_available_methods()
    {
        $methods = AnalysisServiceFactory::getAvailableMethods();

        $this->assertArrayHasKey('sniper', $methods);
        $this->assertArrayHasKey('dynamic_rr', $methods);

        $this->assertEquals('Sniper Analysis', $methods['sniper']);
        $this->assertEquals('Dynamic Risk-Reward Analysis', $methods['dynamic_rr']);
    }

    /**
     * Test that the analysis service factory returns method descriptions.
     */
    public function test_analysis_service_factory_returns_method_descriptions()
    {
        $sniperDescription = AnalysisServiceFactory::getMethodDescription('sniper');
        $this->assertEquals('High precision entry signals based on volume and price action', $sniperDescription);

        $dynamicRRDescription = AnalysisServiceFactory::getMethodDescription('dynamic_rr');
        $this->assertEquals('Dynamic risk-reward calculation using ATR, Fibonacci levels, and support/resistance', $dynamicRRDescription);
    }

    /**
     * Test that sniper service returns correct structure.
     */
    public function test_sniper_service_returns_correct_structure()
    {
        $service = new SniperService();
        $result = $service->analyze('BTCUSDT');

        $this->assertTrue(isset($result->title));
        $this->assertTrue(isset($result->signal));
        $this->assertTrue(isset($result->confidence));
        $this->assertTrue(isset($result->entry));
        $this->assertTrue(isset($result->stop_loss));
        $this->assertTrue(isset($result->take_profit));
        $this->assertTrue(isset($result->risk_reward));
        $this->assertTrue(isset($result->fee));
        $this->assertTrue(isset($result->potential_profit));
        $this->assertTrue(isset($result->potential_loss));

        // Check signal is valid
        $this->assertContains($result->signal, ['long', 'short', 'hold', 'neutral']);

        // Check confidence is between 0 and 100
        $this->assertGreaterThanOrEqual(0, $result->confidence);
        $this->assertLessThanOrEqual(100, $result->confidence);
    }


    /**
     * Test that dynamic RR service returns correct structure.
     */
    public function test_dynamic_rr_service_returns_correct_structure()
    {
        $service = new DynamicRRService();
        $result = $service->analyze('BTCUSDT');

        $this->assertTrue(isset($result->title));
        $this->assertTrue(isset($result->signal));
        $this->assertTrue(isset($result->confidence));
        $this->assertTrue(isset($result->entry));
        $this->assertTrue(isset($result->stop_loss));
        $this->assertTrue(isset($result->take_profit));
        $this->assertTrue(isset($result->risk_reward));
        $this->assertTrue(isset($result->fee));
        $this->assertTrue(isset($result->potential_profit));
        $this->assertTrue(isset($result->potential_loss));

        // Check signal is valid
        $this->assertContains($result->signal, ['long', 'short', 'hold', 'neutral']);
    }
}
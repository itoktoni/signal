<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Coin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CoinControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * Test that the coin update page loads successfully with different analysis methods.
     */
    public function test_coin_update_page_loads_with_analysis_methods()
    {
        // Create a test coin
        $coin = Coin::create([
            'coin_code' => 'BTCUSDT',
            'coin_watch' => true,
            'coin_price_usd' => 50000,
            'coin_price_idr' => 750000000,
            'coin_entry_usd' => 49000,
            'coin_entry_idr' => 735000000,
            'coin_exchange' => 'Binance',
            'coin_plan' => 'long'
        ]);

        // Test with sniper analysis method
        $response = $this->actingAs($this->user)->get(route('coin.getUpdate', [
            'code' => $coin->coin_code,
            'analyst' => 'sniper'
        ]));

        $response->assertStatus(200);
        $response->assertSee('Sniper Analysis for BTCUSDT');

        // Test with support resistance analysis method
        $response = $this->actingAs($this->user)->get(route('coin.getUpdate', [
            'code' => $coin->coin_code,
            'analyst' => 'support_resistance'
        ]));

        $response->assertStatus(200);
        $response->assertSee('Support/Resistance Analysis for BTCUSDT');

        // Test with dynamic RR analysis method
        $response = $this->actingAs($this->user)->get(route('coin.getUpdate', [
            'code' => $coin->coin_code,
            'analyst' => 'dynamic_rr'
        ]));

        $response->assertStatus(200);
        $response->assertSee('Dynamic RR Analysis for BTCUSDT');
    }

    /**
     * Test that the coin update page loads with default analysis method when invalid method is provided.
     */
    public function test_coin_update_page_loads_with_default_method_when_invalid_method_provided()
    {
        // Create a test coin
        $coin = Coin::create([
            'coin_code' => 'ETHUSDT',
            'coin_watch' => true,
            'coin_price_usd' => 3000,
            'coin_price_idr' => 45000000,
            'coin_entry_usd' => 2900,
            'coin_entry_idr' => 43500000,
            'coin_exchange' => 'Binance',
            'coin_plan' => 'short'
        ]);

        // Test with invalid analysis method (should default to sniper)
        $response = $this->actingAs($this->user)->get(route('coin.getUpdate', [
            'code' => $coin->coin_code,
            'analyst' => 'invalid_method'
        ]));

        $response->assertStatus(200);
        $response->assertSee('Sniper Analysis for ETHUSDT');
    }

    /**
     * Test that the coin update page loads when no analyst method is provided.
     */
    public function test_coin_update_page_loads_with_default_method_when_no_method_provided()
    {
        // Create a test coin
        $coin = Coin::create([
            'coin_code' => 'BNBUSDT',
            'coin_watch' => false,
            'coin_price_usd' => 400,
            'coin_price_idr' => 6000000,
            'coin_entry_usd' => 390,
            'coin_entry_idr' => 5850000,
            'coin_exchange' => 'Binance',
            'coin_plan' => 'long' // Changed from 'hold' to 'long' since only 'long' and 'short' are allowed
        ]);

        // Test without analyst method (should default to sniper)
        $response = $this->actingAs($this->user)->get(route('coin.getUpdate', [
            'code' => $coin->coin_code
        ]));

        $response->assertStatus(200);
        $response->assertSee('Sniper Analysis for BNBUSDT');
    }
}
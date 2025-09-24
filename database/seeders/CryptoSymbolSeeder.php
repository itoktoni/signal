<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Services\BinanceService;
use Illuminate\Support\Facades\Log;

class CryptoSymbolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔄 Setting up crypto symbols...');

        // First try to fetch from Binance API
        $binanceService = new BinanceService();
        $exchangeInfo = $binanceService->fetchExchangeInfo();

        if ($exchangeInfo) {
            $this->command->info('✅ Successfully fetched exchange info from Binance API');

            // Process and store symbols
            $processedCount = $binanceService->processAndStoreSymbols($exchangeInfo);

            if ($processedCount > 0) {
                $this->command->info("✅ Successfully processed and stored {$processedCount} crypto symbols");
            } else {
                $this->command->warn('⚠️ No symbols were processed. Check filters and API response.');
            }
        } else {
            $this->command->warn('⚠️ Could not fetch from Binance API. Adding common symbols manually...');

            // Add common symbols manually
            $this->addCommonSymbols();
        }

        // Display summary
        $totalSymbols = \App\Models\CryptoSymbol::count();
        $activeSymbols = \App\Models\CryptoSymbol::active()->count();
        $usdtPairs = \App\Models\CryptoSymbol::usdtPairs()->count();

        $this->command->info("📊 Database Summary:");
        $this->command->info("   • Total symbols: {$totalSymbols}");
        $this->command->info("   • Active symbols: {$activeSymbols}");
        $this->command->info("   • USDT pairs: {$usdtPairs}");

        $this->command->info('🎉 Crypto symbols seeding completed!');
    }

    /**
     * Add common crypto symbols manually
     */
    private function addCommonSymbols()
    {
        $commonSymbols = [
            ['symbol' => 'HYPEUSDT', 'base_asset' => 'HYPE', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'BTCUSDT', 'base_asset' => 'BTC', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'ETHUSDT', 'base_asset' => 'ETH', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'BNBUSDT', 'base_asset' => 'BNB', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'ADAUSDT', 'base_asset' => 'ADA', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'SOLUSDT', 'base_asset' => 'SOL', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'XRPUSDT', 'base_asset' => 'XRP', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'DOTUSDT', 'base_asset' => 'DOT', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'AVAXUSDT', 'base_asset' => 'AVAX', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'MATICUSDT', 'base_asset' => 'MATIC', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'LINKUSDT', 'base_asset' => 'LINK', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'UNIUSDT', 'base_asset' => 'UNI', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'LTCUSDT', 'base_asset' => 'LTC', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'ALGOUSDT', 'base_asset' => 'ALGO', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
            ['symbol' => 'ATOMUSDT', 'base_asset' => 'ATOM', 'quote_asset' => 'USDT', 'status' => 'TRADING', 'is_spot_trading_allowed' => true],
        ];

        $addedCount = 0;
        foreach ($commonSymbols as $symbolData) {
            $symbol = \App\Models\CryptoSymbol::firstOrCreate(
                ['symbol' => $symbolData['symbol']],
                array_merge($symbolData, [
                    'is_margin_trading_allowed' => false,
                    'last_fetched_at' => now(),
                ])
            );

            if ($symbol->wasRecentlyCreated) {
                $addedCount++;
            }
        }

        $this->command->info("✅ Added {$addedCount} common crypto symbols manually");
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BinanceService;
use App\Models\CryptoSymbol;
use Illuminate\Support\Facades\Log;

class UpdateCryptoSymbols extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:update-symbols {--force : Force update all symbols}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update crypto symbols from Binance API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Updating crypto symbols from Binance...');

        $binanceService = new BinanceService();
        $force = $this->option('force');

        // Fetch exchange info from Binance
        $exchangeInfo = $binanceService->fetchExchangeInfo();

        if (!$exchangeInfo) {
            $this->error('❌ Failed to fetch exchange info from Binance API');
            return Command::FAILURE;
        }

        $this->info('✅ Successfully fetched exchange info from Binance');

        // Process and store symbols
        $processedCount = $binanceService->processAndStoreSymbols($exchangeInfo);

        if ($processedCount > 0) {
            $this->info("✅ Successfully processed and stored {$processedCount} crypto symbols");
        } else {
            $this->warn('⚠️ No symbols were processed. Check filters and API response.');
            return Command::FAILURE;
        }

        // Display summary
        $totalSymbols = CryptoSymbol::count();
        $activeSymbols = CryptoSymbol::active()->count();
        $usdtPairs = CryptoSymbol::usdtPairs()->count();

        $this->info("📊 Database Summary:");
        $this->info("   • Total symbols: {$totalSymbols}");
        $this->info("   • Active symbols: {$activeSymbols}");
        $this->info("   • USDT pairs: {$usdtPairs}");

        // Check for HYPEUSDT specifically
        $hypeUsdt = CryptoSymbol::where('symbol', 'HYPEUSDT')->first();
        if ($hypeUsdt) {
            $this->info("✅ HYPEUSDT found in database:");
            $this->info("   • Status: {$hypeUsdt->status}");
            $this->info("   • Base Asset: {$hypeUsdt->base_asset}");
            $this->info("   • Quote Asset: {$hypeUsdt->quote_asset}");
            $this->info("   • Spot Trading Allowed: " . ($hypeUsdt->is_spot_trading_allowed ? 'Yes' : 'No'));
        } else {
            $this->warn("⚠️ HYPEUSDT not found in database");
        }

        $this->info('🎉 Crypto symbols update completed successfully!');
        return Command::SUCCESS;
    }
}

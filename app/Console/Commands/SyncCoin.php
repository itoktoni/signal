<?php

namespace App\Console\Commands;

use App\Analysis\Providers\CoingeckoProvider;
use App\Analysis\Providers\BinanceProvider;
use App\Models\Coin;
use App\Models\Symbol;
use Illuminate\Console\Command;

class SyncCoin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:coin
        {--all : Fetch all coins from provider (no limit)}
        {--limit=1000 : Number of coins to fetch and sync to database}
        {--truncate : Truncate existing coin table before sync}
        {--provider=coingecko : API provider to use (coingecko, binance)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch cryptocurrency list from CoinGecko API and sync to database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $providerCode = strtolower($this->option('provider'));
        $all = $this->option('all');
        $limit = (int) $this->option('limit');
        $truncate = $this->option('truncate') === true || $this->option('truncate') === 'true' || $this->option('truncate') === 1;

        // Validate provider
        $availableProviders = ['coingecko', 'binance'];
        if (!in_array($providerCode, $availableProviders)) {
            $this->error("❌ Invalid provider '{$providerCode}'. Available providers: " . implode(', ', $availableProviders));
            return 1;
        }

        // Automatically set higher memory limit for Binance provider
        if ($providerCode === 'binance') {
            set_time_limit(0);
            ini_set('memory_limit', '512M');
            ini_set('max_execution_time', '120');
            $this->info('📈 Set unlimited time, 512M memory, and 120s execution time for Binance API');
        }

        // If --all flag is used, set limit to null (no limit)
        if ($all) {
            $limit = null;
        } else {
            // Ensure limit is within reasonable bounds
            $limit = max(1, min($limit, 1000));
        }

        try {
            // Truncate table if requested
            if ($truncate) {
                $this->info('🗑️ Truncating existing coin table...');
                Symbol::where('symbol_provider', $providerCode)->delete();
                $this->info('✅ Coin table truncated');
            }

            // Create provider instance
            $provider = $this->createProvider($providerCode);
            $providerName = $provider->getName();

            $this->info("🔍 Fetching {$providerName} coin list...");

            $limitText = $limit ? $limit : 'all';
            $this->info("📊 Requesting {$limitText} coins from {$providerName} API...");

            // Get coin list from provider
            $coins = $provider->getSymbolInfo();

            if (empty($coins)) {
                $this->error("❌ No coins received from {$providerName} API");
                return 1;
            }

            $this->info("✅ Received " . count($coins) . " coins from {$providerName} API");

            // Process and sync to database
            $synced = $this->syncToDatabase($coins, $limit);

            $this->info('🎉 Database sync completed successfully!');
            $this->info("📊 Total coins synced: {$synced}");

            // Display basic summary
            $totalCoins = Symbol::count();
            $this->info("📋 Database now contains: {$totalCoins} total coins");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error fetching {$providerName} coins: " . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Sync coin list to database
     */
    private function syncToDatabase(array $coins, ?int $limit): int
    {
        $synced = 0;

        $this->info('💾 Saving coins to database...');

        $coinsToProcess = $limit ? array_slice($coins, 0, $limit) : $coins;
        foreach ($coinsToProcess as $coin) {
            $coinData = [
                'symbol_code' => $coin['id'],
                'symbol_coin' => $coin['symbol'], // List endpoint doesn't include prices
                'symbol_name' => $coin['name'],
                'symbol_provider' => $coin['provider'],
            ];

            $existingCoin = Symbol::where('symbol_code', $coin['id'])
                                    ->where('symbol_provider', $coin['provider'])
                                    ->first();

            if ($existingCoin) {
                $existingCoin->update($coinData);
            } else {
                Symbol::create($coinData);
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * Create provider instance based on provider code
     */
    private function createProvider(string $providerCode)
    {
        return match($providerCode) {
            'coingecko' => new CoingeckoProvider(),
            'binance' => new BinanceProvider(),
            default => throw new \InvalidArgumentException("Unknown provider: {$providerCode}")
        };
    }
}

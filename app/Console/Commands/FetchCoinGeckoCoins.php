<?php

namespace App\Console\Commands;

use App\Analysis\Providers\CoinGeckoApiProvider;
use App\Models\Coin;
use Illuminate\Console\Command;

class FetchCoinGeckoCoins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:coin
        {--all : Fetch all coins from CoinGecko (no limit)}
        {--limit=1000 : Number of coins to fetch and sync to database}
        {--truncate : Truncate existing coin table before sync}';

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
        $this->info('🔍 Fetching CoinGecko coin list...');

        $all = $this->option('all');
        $limit = (int) $this->option('limit');
        $truncate = $this->option('truncate');

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
                Coin::truncate();
                $this->info('✅ Coin table truncated');
            }

            $coinGeckoProvider = new CoinGeckoApiProvider();

            $limitText = $limit ? $limit : 'all';
            $this->info("📊 Requesting {$limitText} coins from CoinGecko API...");

            // Get coin list from CoinGecko
            $coins = $coinGeckoProvider->getSymbolInfo();

            if (empty($coins)) {
                $this->error('❌ No coins received from CoinGecko API');
                return 1;
            }

            $this->info('✅ Received ' . count($coins) . ' coins from CoinGecko API');

            // Process and sync to database
            $synced = $this->syncToDatabase($coins, $limit);

            $this->info('🎉 Database sync completed successfully!');
            $this->info("📊 Total coins synced: {$synced}");

            // Display basic summary
            $totalCoins = Coin::count();
            $this->info("📋 Database now contains: {$totalCoins} total coins");

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Error fetching CoinGecko coins: ' . $e->getMessage());
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
                'coin_code' => $coin['id'],
                'coin_symbol' => $coin['symbol'], // List endpoint doesn't include prices
                'coin_name' => $coin['name'],
                'coin_watch' => false,
                'last_analyzed_at' => null,
                'analysis_count' => 0,
            ];

            $existingCoin = Coin::where('coin_code', $coinData['coin_code'])->first();

            if ($existingCoin) {
                $existingCoin->update($coinData);
            } else {
                Coin::create($coinData);
                $synced++;
            }
        }

        return $synced;
    }
}

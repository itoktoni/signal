<?php

namespace Database\Seeders;

use App\Helpers\CurrencyHelper;
use App\Models\Coin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class CryptoSymbolSeeder extends Seeder
{
    public function run(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '120');

        $url = 'https://api.binance.com/api/v3/ticker/price';

        $response = Http::withOptions([
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // force IPv4
            ],
        ])->timeout(60)->get($url);

        if ($response->failed()) {
            $this->command->error("Failed to fetch data from Binance API");
            return;
        }

        $data = $response->json();

        if (!isset($data)) {
            $this->command->error("Invalid response from Binance API");
            return;
        }

        $rateService = new \App\Services\CurrencyRateService();
        $newRate = $rateService->getUSDToIDRRate();

        foreach ($data as $symbol) {
            if(str_contains($symbol['symbol'], 'USDT') && $symbol['price'] > 0)
            {
                Coin::updateOrCreate(
                    ['coin_code' => $symbol['symbol']],
                    [
                        'coin_price_usd' => $symbol['price'],
                        'coin_price_idr' => $symbol['price'] * $newRate,
                    ]
                );
            }

        }

        $this->command->info("✅ Crypto symbols successfully seeded from Binance API");
    }
}

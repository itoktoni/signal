<?php

namespace Database\Seeders;

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

        $url = env('BINANCE_API', 'https://api.binance.com') . '/api/v3/exchangeInfo';

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

        if (!isset($data['symbols'])) {
            $this->command->error("Invalid response from Binance API");
            return;
        }

        foreach ($data['symbols'] as $symbol) {
            Coin::updateOrCreate(
                ['coin_code' => $symbol['symbol']],
                [
                    'coin_base' => $symbol['baseAsset'],
                    'coin_asset' => $symbol['quoteAsset'],
                ]
            );
        }

        $this->command->info("✅ Crypto symbols successfully seeded from Binance API");
    }
}

<?php

namespace App\Analysis\Providers;

use App\Analysis\Contract\MarketDataInterface;
use GuzzleHttp\Client;

class BinanceProvider implements MarketDataInterface
{
    private Client $http;
    private string $baseUrl = 'https://api.binance.com/api/v3';

    public function __construct()
    {
        $this->http = new Client(['timeout' => 10]);
    }

    public function getCode(): string
    {
        return 'binance';
    }

    public function getName(): string
    {
        return 'Binance';
    }

    /*
     [
        1499040000000,      // Open time
        "0.01634790",       // Open
        "0.80000000",       // High
        "0.01575800",       // Low
        "0.01577100",       // Close
        "148976.11427815",  // Volume
        1499644799999,      // Close time
        "2434.19055334",    // Quote asset volume
        308,                // Number of trades
        "1756.87402397",    // Taker buy base asset volume
        "28.46694368",      // Taker buy quote asset volume
        "17928899.62484339" // Ignore.
        ]
    */
    public function getHistoricalData(string $symbol, string $timeframe = '1d', int $limit = 500): array
    {
        $url = $this->baseUrl . "/klines";
        $response = $this->http->get($url, [
            'query' => [
                'symbol'   => strtoupper($symbol),
                'interval' => $timeframe,
                'limit'    => $limit,
            ],
        ]);

        $klines = json_decode($response->getBody()->getContents(), true);

        $formattedData = [];

        foreach ($klines as $kline) {
            $formattedData[] = [
                'time' => $kline[0] / 1000, // Convert ms to seconds
                'open' => (float)$kline[1],
                'high' => (float)$kline[2],
                'low' => (float)$kline[3],
                'close' => (float)$kline[4],
                'volume' => (float)$kline[5],
                'close_time' => (float)$kline[6],
                'quote_asset_volume' => (float)$kline[7],
                'number_of_trade' => (float)$kline[8],
                'taker_buy_base_asset_volume' => (float)$kline[9],
                'taker_buy_quote_asset_volume' => (float)$kline[10],
            ];
        }

        return $formattedData;
    }

    public function getPrice(string $symbol): float
    {
        $url = $this->baseUrl . "/ticker/price";
        $response = $this->http->get($url, [
            'query' => ['symbol' => strtoupper($symbol)],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return (float) $data['price'];
    }

    public function getSymbolInfo(): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '900M');
        ini_set('max_execution_time', '120');

        $url = $this->baseUrl . "/exchangeInfo";
        $response = $this->http->get($url);

        $body = $response->getBody()->getContents();

        // Clear the response body to free memory
        $response = null;

        // Use json_decode with limited depth and options for better memory management
        $data = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);

        // Clear the body string to free memory
        $body = null;

        if (empty($data) || !isset($data['symbols']) || !is_array($data['symbols'])) {
            return [];
        }

        $symbols = [];
        $processedCount = 0;
        $maxSymbols = 1000; // Further reduced limit

        foreach ($data['symbols'] as $symbol) {
            // Only process USDT pairs that are actively trading
            if (isset($symbol['symbol'], $symbol['baseAsset'], $symbol['quoteAsset']) &&
                strtoupper($symbol['quoteAsset']) === 'USDT' &&
                isset($symbol['status']) && $symbol['status'] === 'TRADING') {

                $symbols[] = [
                    'id' => strtolower($symbol['symbol']),
                    'symbol' => strtolower($symbol['baseAsset']),
                    'name' => $symbol['baseAsset'],
                    'provider' => 'binance',
                ];

                $processedCount++;

                // Limit the number of symbols to prevent memory exhaustion
                if ($processedCount >= $maxSymbols) {
                    break;
                }
            }

            // Free memory by unsetting processed symbols
            unset($symbol);
        }

        // Clear the data array to free memory
        $data = null;

        return $symbols;
    }
}

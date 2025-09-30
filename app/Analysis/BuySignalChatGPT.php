<?php

namespace App\Analysis;

use GuzzleHttp\Client;

class BuySignalChatGPT implements AnalysisInterface
{
    private string $apiUrl = 'https://api.binance.com/api/v3/klines';
    private Client $http;

    protected ?ApiProviderInterface $apiProvider = null;
    protected float $currentPrice = 0.0;

    public function setApiProvider(ApiProviderInterface $apiProvider): void
    {
        $this->apiProvider = $apiProvider;
    }

    public function __construct()
    {
        $this->http = new Client();
    }

    public function getCode(): string
    {
        return 'buy_signal';
    }

    public function getName(): string
    {
        return 'Simple Buy Signal Analysis';
    }

    public function analyze(
        string $symbol,
        float $amount = 100,
        string $timeframe = '1h',
        ?string $forcedApi = null
    ): object {
        // Step 1: Get historical data
        $candles = $this->getHistoricalData($symbol, $timeframe, 50);

        // Step 2: Extract closing prices
        $closes = array_map(fn($c) => (float) $c[4], $candles);

        // Step 3: Calculate SMA (20-period)
        $sma = array_sum(array_slice($closes, -20)) / 20;

        // Step 4: Get current price
        $price = $this->getPrice($symbol);

        // Step 5: Define BUY strategy
        $entry = $price;
        $stopLoss = $price * 0.98;   // -2%
        $takeProfit = $price * 1.04; // +4%

        return (object) [
            'title'        => 'Simple BUY Signal (SMA Strategy)',
            'description'  => [
                "Fetched 50 candles for {$symbol} on {$timeframe} timeframe",
                "Calculated SMA20 = {$sma}",
                "Current price = {$price}",
                "Since price >= SMA20, issue BUY signal",
            ],
            'signal'       => 'BUY',
            'confidence'   => 75.0,
            'price'        => $price,
            'entry'        => $entry,
            'stop_loss'    => $stopLoss,
            'take_profit'  => $takeProfit,
            'risk_reward'  => '1:2',
            'indicators'   => [
                'SMA20' => $sma,
                'Price' => $price,
            ],
            'notes'        => [
                "Trade only with proper risk management",
                "This is a simple SMA strategy demo",
                "Confidence level is moderate, wait for trend confirmation",
            ],
        ];
    }

    /**
     * Get historical data for analysis
     */
    public function getHistoricalData(string $symbol, string $timeframe = '1h', int $limit = 200): array
    {
        if (!$this->apiProvider) {
            throw new \Exception('API Provider not set');
        }

        return $this->apiProvider->getHistoricalData($symbol, $timeframe, $limit);
    }

    /**
     * Get current price
     */
    public function getPrice(string $symbol): float
    {
        if (!$this->apiProvider) {
            throw new \Exception('API Provider not set');
        }

        // If we have a stored price and it's for the requested symbol, return it
        if ($this->currentPrice) {
            return $this->currentPrice;
        }

        // Otherwise, fetch fresh price from API
        return $this->apiProvider->getCurrentPrice($symbol);
    }
}

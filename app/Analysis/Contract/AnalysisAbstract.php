<?php

namespace App\Analysis\Contract;

use App\Analysis\Contract\MarketDataInterface;
use App\Models\Coin;

abstract class AnalysisAbstract implements MarketDataInterface
{
    protected MarketDataInterface $provider;

    public function __construct(MarketDataInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Retrieve historical OHLCV (Open, High, Low, Close, Volume) data
     *
     *
     * @param string $symbol    The trading pair
     * @param string $timeframe The timeframe (default '1h')
     * @param int    $limit     Number of data points (default 500)
     *
     * @return array
     * [
     *   [
     *      'time' => $kline[0] / 1000, // Convert ms to seconds
     *      'open' => (float)$kline[1],
     *      'high' => (float)$kline[2],
     *      'low' => (float)$kline[3],
     *      'close' => (float)$kline[4],
     *      'volume' => (float)$kline[5],
     *      'close_time' => (float)$kline[6],
     *      'quote_asset_volume' => (float)$kline[7],
     *      'number_of_trade' => (float)$kline[8],
     *      'taker_buy_base_asset_volume' => (float)$kline[9],
     *      'taker_buy_quote_asset_volume' => (float)$kline[10],
     *   ]
     * ]
     */
    public function getHistoricalData(string $symbol, string $timeframe = '1h', int $limit = 200): array
    {
        $coin = $this->getSymbol($symbol);
        return $this->provider->getHistoricalData($coin, $timeframe, $limit);
    }

    /**
     * Get the current market price for a symbol
     *
     * @param string $symbol The trading pair
     * @return float Current price in USD
     */
    public function getPrice(string $symbol): float
    {
        $coin = $this->getSymbol($symbol);
        return $this->provider->getPrice($coin);
    }

    /**
     * Get the symbol converter
     *
     * @param string $symbol The trading pair
     * @return string Current Symbol in Api Provider
     */
    public function getSymbol(string $symbol): string
    {
        $coin = Coin::find($symbol);

        if(empty($coin))
        {
            return throw new \Exception("Symbol '{$symbol}' not found");
        }

        return $coin->coin_symbol;
    }

    /**
     * Get the unique code identifier for this analysis method
     * (used in UI dropdowns or database storage)
     *
     * Example: 'moving_average', 'support_resistance'
     *
     * @return string
     */
    abstract public function getCode(): string;

    /**
     * Get the human-readable name of this analysis method
     *
     * Example: 'Moving Average Analysis'
     *
     * @return string
     */
    abstract public function getName(): string;

     /**
     * Perform a cryptocurrency analysis and return a standardized result object
     *
     * @param string      $symbol     The trading pair to analyze (e.g., 'BTCUSDT')
     * @param float       $amount     Trading amount in USD
     * @param string      $timeframe  The timeframe for analysis (e.g., '1h', '4h', '1d')
     * @param string|null $forcedApi  Force a specific API provider (optional)
     *
     * @return object {
     *   title: string,             // Analysis title
     *   description: array,        // jelaskan lebih detail tentang bagaimana cara kamu menganalisa, dan step by step yang kamu lakukan untuk menaganalisa coin tersebut
     *   signal: string,            // Trading signal: 'BUY' atau 'SELL' lihat tingkat keberhasilan misalnya buy keberhasilan 70% sedangkan sell 20%, maka tampilkan yang 70%
     *   confidence: float,         // Confidence level (0–100) tingkat akurasi indikator misalnya untuk signal buy, harga dekat dengan support dan rsi sudah diatas harga, pembalikan arah untuk uptrend
     *   score: int,                // spediction score winrate (e.g., '1-100') tingkat keberhasilan recomendasi ini, misalnya dengan semua indikator yang dipakai, semua menunjukan bullish dan harga sangat bagus untuk entry maka hitung semua indikator dan berikan score jika lebih dari 70% maka signal bagus
     *   price: float,              // Current market price in USD
     *   entry: float,              // buat fungsi untuk menghitung kapan yang tepat untuk entry, misalnya kamu merekomendasi buy, harga sekarang $100, dan sudah menembus resistant, kamu bisa merekomendasikan untuk entry saat retrace kebawah misalnya di $98
     *   stop_loss: float,          // misalnya harga sudah berada di support, dan rawan sekali untuk break out karena koin tersebut baru dan kamu bisa melihat history dari perdangan sebelumnya apakah coin ini pernah turun sampai 20% lebih maka kamu bisa merekomendasikan stoploss dibawah sedikit dibawah support
     *   take_profit: float,        // kamu bisa merekomendasikan take provit di next resistant atau menggunakan indicator lain seperti fibonacci retracement
     *   risk_reward: string,       // Risk-reward ratio (e.g., '1:2')
     *   indicators: array,         // Indicators yang kamu gunakan untuk menganalisa, buat key value dalam bentuk array key-value pairs (e.g., ['SMA' => 100, 'EMA' => 50])
     *   historical: array,         // Get History OHLCV
     *   notes: array               // catatan suggestion dari kamu misalnya, jangan entry dulu, tunggu konfirmasi breakout di harga $900 misalnya,
     *   patterns : array,          // Detected patterns dengan details
     *   market_phase : string,     // Current market condition
     *   volatility_factor : float, // ATR-based volatility measure
     *   support_levels : array,    // Identified support levels
     *   resistance_levels : array, // Identified resistance levels
     *   trend_direction : string,  // Bullish/Bearish/Sideways
     *   trend_strength : float,    // Kekuatan trend (0-100)
     * }
     */
    abstract public function analyze(
        string $symbol,
        float $amount = 100,
        string $timeframe = '1h',
        ?string $forcedApi = null
    ): object;
}

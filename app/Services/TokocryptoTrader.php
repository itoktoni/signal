<?php

namespace App\Services;

use App\Models\Trade;
use Illuminate\Support\Facades\Log;

/**
 * TokocryptoTrader Service
 *
 * This service provides a high-level interface for Tokocrypto trading operations.
 * It can be used by controllers, console commands, jobs, or any other Laravel component.
 *
 * Usage examples:
 *
 * // In a controller
 * public function trade(TokocryptoTrader $trader)
 * {
 *     $result = $trader->marketBuy('BTC/USDT', 0.001);
 * }
 *
 * // In a console command
 * public function handle(TokocryptoTrader $trader)
 * {
 *     $trader->limitSell('ETH/USDT', 1.0, 2000.0);
 * }
 *
 * // In a queued job
 * public function handle(TokocryptoTrader $trader)
 * {
 *     $trader->executeTrade($tradeModel);
 * }
 */
class TokocryptoTrader
{
    protected $tradeService;
    protected $tokocryptoIntegration;

    public function __construct(TradeService $tradeService, TokocryptoIntegration $tokocryptoIntegration)
    {
        $this->tradeService = $tradeService;
        $this->tokocryptoIntegration = $tokocryptoIntegration;
    }

    /**
     * Execute a market buy order with base currency amount
     */
    public function marketBuy(string $symbol, float $amount): array
    {
        try {
            Log::info('Executing market buy order', [
                'symbol' => $symbol,
                'amount' => $amount
            ]);

            $order = $this->tokocryptoIntegration->createMarketBuyOrder($symbol, $amount);

            return [
                'success' => true,
                'order' => $order,
                'message' => 'Market buy order executed successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Market buy order failed', [
                'symbol' => $symbol,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a market sell order
     */
    public function marketSell(string $symbol, float $amount): array
    {
        try {
            Log::info('Executing market sell order', [
                'symbol' => $symbol,
                'amount' => $amount
            ]);

            $order = $this->tokocryptoIntegration->createMarketSellOrder($symbol, $amount);

            return [
                'success' => true,
                'order' => $order,
                'message' => 'Market sell order executed successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Market sell order failed', [
                'symbol' => $symbol,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a limit buy order
     */
    public function limitBuy(string $symbol, float $amount, float $price): array
    {
        try {
            Log::info('Executing limit buy order', [
                'symbol' => $symbol,
                'amount' => $amount,
                'price' => $price
            ]);

            $order = $this->tokocryptoIntegration->createLimitBuyOrder($symbol, $amount, $price);

            return [
                'success' => true,
                'order' => $order,
                'message' => 'Limit buy order executed successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Limit buy order failed', [
                'symbol' => $symbol,
                'amount' => $amount,
                'price' => $price,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a limit sell order
     */
    public function limitSell(string $symbol, float $amount, float $price): array
    {
        try {
            Log::info('Executing limit sell order', [
                'symbol' => $symbol,
                'amount' => $amount,
                'price' => $price
            ]);

            $order = $this->tokocryptoIntegration->createLimitSellOrder($symbol, $amount, $price);

            return [
                'success' => true,
                'order' => $order,
                'message' => 'Limit sell order executed successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Limit sell order failed', [
                'symbol' => $symbol,
                'amount' => $amount,
                'price' => $price,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a market buy order with USDT amount
     */
    public function usdMarketBuy(string $symbol, float $usdAmount): array
    {
        try {
            Log::info('Executing USDT market buy order', [
                'symbol' => $symbol,
                'usd_amount' => $usdAmount
            ]);

            // Get current price for conversion
            $ticker = $this->tokocryptoIntegration->getTicker($symbol);
            if (!$ticker || !isset($ticker['last'])) {
                throw new \Exception('Unable to get current price for USDT conversion');
            }

            $currentPrice = (float)$ticker['last'];
            $amount = $usdAmount / $currentPrice;

            $order = $this->tokocryptoIntegration->createMarketBuyOrder($symbol, $amount);

            return [
                'success' => true,
                'order' => $order,
                'usd_amount' => $usdAmount,
                'base_amount' => $amount,
                'conversion_price' => $currentPrice,
                'message' => "USDT market buy order executed successfully (${usdAmount} USDT = ${amount} base currency)"
            ];
        } catch (\Exception $e) {
            Log::error('USDT market buy order failed', [
                'symbol' => $symbol,
                'usd_amount' => $usdAmount,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a market sell order with USDT amount
     */
    public function usdMarketSell(string $symbol, float $usdAmount): array
    {
        try {
            Log::info('Executing USDT market sell order', [
                'symbol' => $symbol,
                'usd_amount' => $usdAmount
            ]);

            // Get current price for conversion
            $ticker = $this->tokocryptoIntegration->getTicker($symbol);
            if (!$ticker || !isset($ticker['last'])) {
                throw new \Exception('Unable to get current price for USDT conversion');
            }

            $currentPrice = (float)$ticker['last'];
            $amount = $usdAmount / $currentPrice;

            $order = $this->tokocryptoIntegration->createMarketSellOrder($symbol, $amount);

            return [
                'success' => true,
                'order' => $order,
                'usd_amount' => $usdAmount,
                'base_amount' => $amount,
                'conversion_price' => $currentPrice,
                'message' => "USDT market sell order executed successfully (${usdAmount} USDT = ${amount} base currency)"
            ];
        } catch (\Exception $e) {
            Log::error('USDT market sell order failed', [
                'symbol' => $symbol,
                'usd_amount' => $usdAmount,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a limit buy order with USDT amount
     */
    public function usdLimitBuy(string $symbol, float $usdAmount, float $price): array
    {
        try {
            Log::info('Executing USDT limit buy order', [
                'symbol' => $symbol,
                'usd_amount' => $usdAmount,
                'price' => $price
            ]);

            $amount = $usdAmount / $price;
            $order = $this->tokocryptoIntegration->createLimitBuyOrder($symbol, $amount, $price);

            return [
                'success' => true,
                'order' => $order,
                'usd_amount' => $usdAmount,
                'base_amount' => $amount,
                'limit_price' => $price,
                'message' => "USDT limit buy order executed successfully (${usdAmount} USDT = ${amount} base currency at ${price})"
            ];
        } catch (\Exception $e) {
            Log::error('USDT limit buy order failed', [
                'symbol' => $symbol,
                'usd_amount' => $usdAmount,
                'price' => $price,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a limit sell order with USDT amount
     */
    public function usdLimitSell(string $symbol, float $usdAmount, float $price): array
    {
        try {
            Log::info('Executing USDT limit sell order', [
                'symbol' => $symbol,
                'usd_amount' => $usdAmount,
                'price' => $price
            ]);

            $amount = $usdAmount / $price;
            $order = $this->tokocryptoIntegration->createLimitSellOrder($symbol, $amount, $price);

            return [
                'success' => true,
                'order' => $order,
                'usd_amount' => $usdAmount,
                'base_amount' => $amount,
                'limit_price' => $price,
                'message' => "USDT limit sell order executed successfully (${usdAmount} USDT = ${amount} base currency at ${price})"
            ];
        } catch (\Exception $e) {
            Log::error('USDT limit sell order failed', [
                'symbol' => $symbol,
                'usd_amount' => $usdAmount,
                'price' => $price,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a trade using a Trade model
     */
    public function executeTrade(Trade $trade): array
    {
        return $this->tradeService->executeTrade($trade);
    }

    /**
     * Cancel a trade
     */
    public function cancelTrade(Trade $trade): array
    {
        return $this->tradeService->cancelTrade($trade);
    }

    /**
     * Get trade status
     */
    public function getTradeStatus(Trade $trade): array
    {
        return $this->tradeService->getTradeStatus($trade);
    }

    /**
     * Get account balance
     */
    public function getBalance(string $currency = null): array
    {
        try {
            $balance = $this->tokocryptoIntegration->getBalance();

            if ($currency) {
                return [
                    'success' => true,
                    'balance' => $balance['total'][$currency] ?? 0,
                    'currency' => $currency
                ];
            }

            return [
                'success' => true,
                'balance' => $balance['total'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get ticker information
     */
    public function getTicker(string $symbol): array
    {
        try {
            $ticker = $this->tokocryptoIntegration->getTicker($symbol);

            return [
                'success' => true,
                'ticker' => $ticker
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available trading symbols
     */
    public function getAvailableSymbols(): array
    {
        try {
            return [
                'success' => true,
                'symbols' => $this->tokocryptoIntegration->getAvailableSymbols()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test exchange connection
     */
    public function testConnection(): array
    {
        return $this->tradeService->testConnection();
    }

    /**
     * Sync all active trades with exchange
     */
    public function syncActiveTrades(): array
    {
        try {
            $trades = Trade::whereIn('status', ['open', 'filled'])->get();
            $synced = 0;

            foreach ($trades as $trade) {
                $status = $this->tradeService->getTradeStatus($trade);

                if ($status['success']) {
                    $trade->updateStatus($status['status'], $status['exchange_response']);
                    $trade->price = $status['price'] ?? $trade->price;
                    $trade->cost = $status['cost'] ?? $trade->cost;
                    $trade->fee = $status['fee'] ?? $trade->fee;
                    $trade->fee_currency = $status['fee_currency'] ?? $trade->fee_currency;
                    $trade->save();
                    $synced++;
                }
            }

            return [
                'success' => true,
                'synced_count' => $synced,
                'message' => "Synced {$synced} trades with exchange"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
<?php

namespace App\Services;

use App\Models\Trade;
use ccxt\tokocrypto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TradeService
{
    protected $exchange;
    protected $exchangeName = 'tokocrypto';

    public function __construct()
    {
        // Only set credentials if they are provided
        $config = [
            'timeout' => 30000,
            'enableRateLimit' => true,
            'rateLimit' => 1000,
            'options' => [
                'adjustForTimeDifference' => false, // Disable to avoid publicGetTime issues
                'recvWindow' => 10000, // Add recvWindow for compatibility
            ],
        ];

        $apiKey = config('services.tokocrypto.api_key', env('TOKOCRYPTO_API_KEY'));
        $secret = config('services.tokocrypto.secret', env('TOKOCRYPTO_API_SECRET'));

        if (!empty($apiKey) && !empty($secret)) {
            $config['apiKey'] = $apiKey;
            $config['secret'] = $secret;
            $config['sandbox'] = config('services.tokocrypto.sandbox', env('TOKOCRYPTO_SANDBOX', true));
        }

        $this->exchange = new tokocrypto($config);

        // Don't load markets in constructor to avoid initialization issues
        // Markets will be loaded on-demand when needed
    }

    /**
     * Load available markets from exchange
     */
    protected function loadMarkets()
    {
        try {
            $markets = Cache::remember('tokocrypto_markets', 3600, function () {
                try {
                    return $this->exchange->loadMarkets();
                } catch (\Exception $e) {
                    Log::warning('Failed to load markets, trying fetchMarkets', [
                        'error' => $e->getMessage()
                    ]);

                    // Try alternative method if loadMarkets fails
                    try {
                        return $this->exchange->fetchMarkets();
                    } catch (\Exception $e2) {
                        Log::error('Both loadMarkets and fetchMarkets failed', [
                            'error1' => $e->getMessage(),
                            'error2' => $e2->getMessage()
                        ]);
                        throw $e2;
                    }
                }
            });
            return $markets;
        } catch (\Exception $e) {
            Log::error('Failed to load Tokocrypto markets', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get available trading pairs
     */
    public function getAvailableTradingPairs()
    {
        try {
            $markets = $this->loadMarkets();

            $pairs = [];
            foreach ($markets as $symbol => $market) {
                if ($market['active'] && in_array($market['type'], ['spot', 'future'])) {
                    $pairs[$symbol] = $symbol . ' (' . ($market['base'] ?? 'N/A') . '/' . ($market['quote'] ?? 'N/A') . ')';
                }
            }

            return $pairs;
        } catch (\Exception $e) {
            Log::error('Failed to get trading pairs', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Execute trade on exchange
     */
    public function executeTrade(Trade $trade)
    {
        try {
            // Check if we have API credentials
            if (empty($this->exchange->apiKey)) {
                return [
                    'success' => false,
                    'error' => 'API credentials not configured. Please set TOKOCRYPTO_API_KEY and TOKOCRYPTO_SECRET in your .env file.',
                ];
            }
            // Prepare order parameters
            $orderParams = [
                'symbol' => $trade->symbol,
                'type' => $trade->type,
                'side' => $trade->side,
                'amount' => $trade->amount,
            ];

            // Add price for limit orders
            if (in_array($trade->type, ['limit', 'stop_limit']) && $trade->price) {
                $orderParams['price'] = $trade->price;
            }

            // Add stop price for stop orders
            if (in_array($trade->type, ['stop', 'stop_limit']) && $trade->stop_loss) {
                $orderParams['stopPrice'] = $trade->stop_loss;
            }

            Log::info('Executing trade on Tokocrypto', [
                'trade_id' => $trade->trade_id,
                'params' => $orderParams
            ]);

            // Execute order
            $order = $this->exchange->createOrder(
                $orderParams['symbol'],
                $orderParams['type'],
                $orderParams['side'],
                $orderParams['amount'],
                $orderParams['price'] ?? null,
                $orderParams
            );

            Log::info('Trade executed successfully', [
                'trade_id' => $trade->trade_id,
                'exchange_order' => $order
            ]);

            return [
                'success' => true,
                'order_id' => $order['id'],
                'exchange_response' => $order,
            ];

        } catch (\Exception $e) {
            Log::error('Trade execution failed', [
                'trade_id' => $trade->trade_id,
                'error' => $e->getMessage(),
                'params' => $orderParams ?? []
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel trade on exchange
     */
    public function cancelTrade(Trade $trade)
    {
        try {
            // Check if we have API credentials
            if (empty($this->exchange->apiKey)) {
                return [
                    'success' => false,
                    'error' => 'API credentials not configured. Please set TOKOCRYPTO_API_KEY and TOKOCRYPTO_SECRET in your .env file.',
                ];
            }

            if (!$trade->exchange_order_id) {
                return [
                    'success' => false,
                    'error' => 'No exchange order ID found',
                ];
            }

            Log::info('Cancelling trade on Tokocrypto', [
                'trade_id' => $trade->trade_id,
                'exchange_order_id' => $trade->exchange_order_id
            ]);

            // Cancel order
            $result = $this->exchange->cancelOrder($trade->exchange_order_id, $trade->symbol);

            Log::info('Trade cancelled successfully', [
                'trade_id' => $trade->trade_id,
                'exchange_response' => $result
            ]);

            return [
                'success' => true,
                'exchange_response' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('Trade cancellation failed', [
                'trade_id' => $trade->trade_id,
                'exchange_order_id' => $trade->exchange_order_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get trade status from exchange
     */
    public function getTradeStatus(Trade $trade)
    {
        try {
            // Check if we have API credentials
            if (empty($this->exchange->apiKey)) {
                return [
                    'success' => false,
                    'error' => 'API credentials not configured. Please set TOKOCRYPTO_API_KEY and TOKOCRYPTO_SECRET in your .env file.',
                ];
            }

            if (!$trade->exchange_order_id) {
                return [
                    'success' => false,
                    'error' => 'No exchange order ID found',
                ];
            }

            Log::info('Fetching trade status from Tokocrypto', [
                'trade_id' => $trade->trade_id,
                'exchange_order_id' => $trade->exchange_order_id
            ]);

            // Fetch order status
            $order = $this->exchange->fetchOrder($trade->exchange_order_id, $trade->symbol);

            $status = $this->mapExchangeStatus($order['status']);
            $price = $order['price'] ?? null;
            $cost = $order['cost'] ?? null;
            $fee = $order['fee']['cost'] ?? null;
            $feeCurrency = $order['fee']['currency'] ?? null;

            Log::info('Trade status fetched successfully', [
                'trade_id' => $trade->trade_id,
                'exchange_status' => $order['status'],
                'mapped_status' => $status
            ]);

            return [
                'success' => true,
                'status' => $status,
                'price' => $price,
                'cost' => $cost,
                'fee' => $fee,
                'fee_currency' => $feeCurrency,
                'exchange_response' => $order,
            ];

        } catch (\Exception $e) {
            Log::error('Trade status fetch failed', [
                'trade_id' => $trade->trade_id,
                'exchange_order_id' => $trade->exchange_order_id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Map exchange status to internal status
     */
    protected function mapExchangeStatus($exchangeStatus)
    {
        return match(strtolower($exchangeStatus)) {
            'open', 'new' => 'open',
            'filled', 'closed' => 'filled',
            'cancelled', 'canceled' => 'cancelled',
            'rejected' => 'rejected',
            'partially_filled' => 'filled',
            default => 'unknown'
        };
    }

    /**
     * Get account balance
     */
    public function getBalance($currency = null)
    {
        try {
            // Check if we have API credentials
            if (empty($this->exchange->apiKey)) {
                Log::warning('Cannot fetch balance: API credentials not configured');
                return $currency ? 0 : [];
            }

            $balance = $this->exchange->fetchBalance();

            // Validate response format
            if (!is_array($balance)) {
                Log::error('Invalid balance response format', ['response_type' => gettype($balance)]);
                return $currency ? 0 : [];
            }

            if ($currency) {
                return $balance['total'][$currency] ?? 0;
            }

            return $balance['total'] ?? [];
        } catch (\Exception $e) {
            // Handle JSON parsing errors specifically
            if (str_contains($e->getMessage(), 'JSON') || str_contains($e->getMessage(), 'token')) {
                Log::error('Balance fetch failed due to JSON parsing error', [
                    'error' => $e->getMessage(),
                    'currency' => $currency,
                    'suggestion' => 'Check API credentials and network connectivity'
                ]);
            } else {
                Log::error('Failed to fetch balance', [
                    'error' => $e->getMessage(),
                    'currency' => $currency
                ]);
            }

            return $currency ? 0 : [];
        }
    }

    /**
     * Get ticker information
     */
    public function getTicker($symbol)
    {
        try {
            return $this->exchange->fetchTicker($symbol);
        } catch (\Exception $e) {
            Log::error('Failed to fetch ticker', [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get recent trades for a symbol
     */
    public function getRecentTrades($symbol, $limit = 100)
    {
        try {
            return $this->exchange->fetchTrades($symbol, null, $limit);
        } catch (\Exception $e) {
            Log::error('Failed to fetch recent trades', [
                'symbol' => $symbol,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Test exchange connection
     */
    public function testConnection()
    {
        try {
            // Try to load markets as a connection test
            $this->exchange->loadMarkets();

            return [
                'success' => true,
                'message' => 'Connection successful',
                'exchange' => $this->exchangeName,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exchange' => $this->exchangeName,
            ];
        }
    }

    /**
     * Get exchange instance for direct access
     */
    public function getExchange()
    {
        return $this->exchange;
    }
}
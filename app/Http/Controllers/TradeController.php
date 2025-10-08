<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Models\Coin;
use App\Services\TradeService;
use App\Services\TokocryptoIntegration;
use App\Traits\ControllerHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use ccxt\tokocrypto;

class TradeController extends Controller
{
    use ControllerHelper;

    protected $model;
    protected $tradeService;
    protected $tokocryptoIntegration;

    public function __construct(Trade $model, TradeService $tradeService, TokocryptoIntegration $tokocryptoIntegration)
    {
        $this->model = $model;
        $this->tradeService = $tradeService;
        $this->tokocryptoIntegration = $tokocryptoIntegration;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return redirect()->route($this->module('getData'));
    }

    /**
     * Display trades data with filtering and pagination
     */
    public function getData()
    {
        $perPage = request('perpage', 10);
        $data = $this->model->filter(request())->orderBy('created_at', 'DESC')->paginate($perPage);
        $data->appends(request()->query());

        return $this->views($this->module(), [
            'data' => $data,
        ]);
    }

    /**
     * Show the Tokocrypto trading interface
     */
    public function getCreate()
    {
        // Get available coins for trading
        $coins = Coin::where('coin_watch', true)
                    ->orderBy('coin_symbol')
                    ->pluck('coin_name', 'coin_symbol');

        // Get available trading pairs from exchange
        $tradingPairs = $this->tradeService->getAvailableTradingPairs();

        // Get popular trading pairs for quick selection
        $popularPairs = $this->getPopularTradingPairs();

        return view('trade.create', [
            'coins' => $coins,
            'trading_pairs' => $tradingPairs,
            'popular_pairs' => $popularPairs,
        ]);
    }

    /**
     * Handle AJAX requests for trading interface
     */
    public function handleTradingAjax()
    {
        try {
            $action = request('action');

            switch ($action) {
                case 'get_ticker':
                    return $this->getTickerData();

                case 'get_balance':
                    return $this->getBalanceData();

                case 'get_order_book':
                    return $this->getOrderBookData();

                case 'place_order':
                    // Verify CSRF token for POST requests
                    if (request()->isMethod('post')) {
                        $token = request('_token') ?: request()->header('X-CSRF-TOKEN');
                        if (!$token || !hash_equals(csrf_token(), $token)) {
                            return response()->json(['success' => false, 'error' => 'CSRF token mismatch'], 419);
                        }
                    }
                    return $this->placeOrderData();

                case 'get_trading_pairs':
                    return $this->getTradingPairsForCoin();

                default:
                    return response()->json(['success' => false, 'error' => 'Unknown action: ' . $action]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ticker data
     */
    private function getTickerData()
    {
        try {
            $symbol = request('symbol', 'BTC/USDT');
            $ticker = $this->tokocryptoIntegration->getTicker($symbol);

            if (!$ticker) {
                throw new \Exception('Unable to fetch ticker data');
            }

            return response()->json(['success' => true, 'data' => $ticker]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get balance data
     */
    private function getBalanceData()
    {
        try {
            if (empty(config('services.tokocrypto.api_key')) || empty(config('services.tokocrypto.api_secret'))) {
                throw new \Exception('API credentials not configured');
            }

            $balance = $this->tokocryptoIntegration->getBalance();

            if ($balance === null) {
                throw new \Exception('Unable to fetch balance data');
            }

            return response()->json(['success' => true, 'data' => $balance]);
        } catch (\Exception $e) {
            // Handle JSON parsing errors specifically for AJAX responses
            if (str_contains($e->getMessage(), 'JSON') || str_contains($e->getMessage(), 'token') || str_contains($e->getMessage(), 'Invalid API response')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unable to load balance - please check your API credentials are configured correctly'
                ]);
            }

            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Get order book data
     */
    private function getOrderBookData()
    {
        try {
            $symbol = request('symbol', 'BTC/USDT');
            $limit = (int)request('limit', 10);
            $orderBook = $this->tokocryptoIntegration->getOrderBook($symbol, $limit);

            if (!$orderBook) {
                throw new \Exception('Unable to fetch order book data');
            }

            return response()->json(['success' => true, 'data' => $orderBook]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Place order
     */
    private function placeOrderData()
    {
        try {
            if (empty(config('services.tokocrypto.api_key')) || empty(config('services.tokocrypto.secret'))) {
                throw new \Exception('API credentials not configured');
            }

            $side = request('side', 'buy');
            $type = request('type', 'market');
            $symbol = request('symbol', 'BTC/USDT');
            $amountMode = request('amount_mode', 'base');
            $usdAmount = (float)request('usd_amount', 0);
            $amount = (float)request('amount', 0);
            $price = (float)request('price', 0);

            // Handle amount based on mode
            if ($amountMode === 'usd' && $usdAmount > 0) {
                // Get current price for conversion
                $ticker = $this->tokocryptoIntegration->getTicker($symbol);
                if (!$ticker || !isset($ticker['last'])) {
                    throw new \Exception('Unable to get current price for USDT conversion');
                }

                $currentPrice = (float)$ticker['last'];
                $amount = $usdAmount / $currentPrice;

                if ($amount <= 0) {
                    throw new \Exception('Converted amount must be greater than 0');
                }
            } elseif ($amount <= 0) {
                throw new \Exception('Amount must be greater than 0');
            }

            $result = null;
            if ($type === 'limit') {
                if ($price <= 0) {
                    throw new \Exception('Price must be greater than 0 for limit orders');
                }
                $result = $side === 'buy'
                    ? $this->tokocryptoIntegration->createLimitBuyOrder($symbol, $amount, $price)
                    : $this->tokocryptoIntegration->createLimitSellOrder($symbol, $amount, $price);
            } else {
                $result = $side === 'buy'
                    ? $this->tokocryptoIntegration->createMarketBuyOrder($symbol, $amount)
                    : $this->tokocryptoIntegration->createMarketSellOrder($symbol, $amount);
            }

            if (!$result) {
                throw new \Exception('Failed to place order');
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Create a new trade
     */
    public function postCreate(Request $request)
    {
        $validated = $request->validate([
            'symbol' => 'required|string',
            'side' => 'required|in:buy,sell',
            'type' => 'required|in:market,limit,stop,stop_limit',
            'amount' => 'required|numeric|min:0.00000001',
            'price' => 'nullable|numeric|min:0',
            'trading_plan_id' => 'nullable|string',
            'analysis_method' => 'nullable|string',
            'analysis_result' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Create trade record
            $trade = $this->model->create($validated);

            // Execute trade on exchange if auto-execute is enabled
            if ($request->has('auto_execute') && $request->auto_execute) {
                $result = $this->tradeService->executeTrade($trade);

                if ($result['success']) {
                    $trade->updateStatus('open', $result['exchange_response'] ?? null);
                    $trade->exchange_order_id = $result['order_id'] ?? null;
                    $trade->save();
                } else {
                    $trade->updateStatus('rejected', ['error' => $result['error']]);
                    throw new \Exception($result['error']);
                }
            }

            DB::commit();

            return redirect()->route($this->module('getData'))
                ->with('success', 'Trade created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Trade creation failed', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            return back()->withInput()
                ->with('error', 'Failed to create trade: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified trade
     */
    public function getShow($tradeId)
    {
        $model = $this->model->find($tradeId);

        if (!$model) {
            return redirect()->route($this->module('getData'))
                ->with('error', 'Trade not found');
        }

        return $this->views($this->module(), [
            'model' => $model,
        ]);
    }

    /**
     * Show the form for editing the specified trade
     */
    public function getUpdate($tradeId)
    {
        $model = $this->model->find($tradeId);

        if (!$model) {
            return redirect()->route($this->module('getData'))
                ->with('error', 'Trade not found');
        }

        // Get available coins for trading
        $coins = Coin::where('coin_watch', true)->pluck('coin_symbol', 'coin_code');

        // Get available trading pairs from exchange
        $tradingPairs = $this->tradeService->getAvailableTradingPairs();

        // Get analysis methods for dropdown
        $analysisMethods = [
            'sniper' => 'Sniper',
            'support_resistant' => 'Support & Resistance',
            'dynamic_rr' => 'Dynamic Risk Reward',
            'keltner_channel' => 'Keltner Channel',
        ];

        return $this->views($this->module(), [
            'model' => $model,
            'coins' => $coins,
            'trading_pairs' => $tradingPairs,
            'analysis_methods' => $analysisMethods,
        ]);
    }

    /**
     * Update the specified trade
     */
    public function postUpdate(Request $request, $tradeId)
    {
        $model = $this->model->find($tradeId);

        if (!$model) {
            return redirect()->route($this->module('getData'))
                ->with('error', 'Trade not found');
        }

        $validated = $request->validate([
            'symbol' => 'required|string',
            'side' => 'required|in:buy,sell',
            'type' => 'required|in:market,limit,stop,stop_limit',
            'amount' => 'nullable|numeric|min:0.00000001',
            'usd_amount' => 'nullable|numeric|min:0.01',
            'amount_mode' => 'required|in:base,usd',
            'price' => 'nullable|numeric|min:0',
            'trading_plan_id' => 'nullable|string',
            'analysis_method' => 'nullable|string',
            'analysis_result' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        // Ensure either amount or usd_amount is provided based on mode
        if ($validated['amount_mode'] === 'usd' && (!$validated['usd_amount'] || $validated['usd_amount'] <= 0)) {
            return back()->withErrors(['usd_amount' => 'USDT amount is required when using USDT mode'])->withInput();
        }

        if ($validated['amount_mode'] === 'base' && (!$validated['amount'] || $validated['amount'] <= 0)) {
            return back()->withErrors(['amount' => 'Amount is required when using base currency mode'])->withInput();
        }

        try {
            // Update trade record
            $model->update($validated);

            return redirect()->route($this->module('getShow'), $tradeId)
                ->with('success', 'Trade updated successfully');

        } catch (\Exception $e) {
            Log::error('Trade update failed', [
                'trade_id' => $tradeId,
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            return back()->withInput()
                ->with('error', 'Failed to update trade: ' . $e->getMessage());
        }
    }

    /**
     * Execute trade on exchange
     */
    public function postExecute($tradeId)
    {
        $model = $this->model->find($tradeId);

        if (!$model) {
            return response()->json(['success' => false, 'error' => 'Trade not found'], 404);
        }

        if ($model->status !== 'pending') {
            return response()->json(['success' => false, 'error' => 'Trade is not in pending status'], 400);
        }

        try {
            $result = $this->tradeService->executeTrade($model);

            if ($result['success']) {
                $model->updateStatus('open', $result['exchange_response'] ?? null);
                $model->exchange_order_id = $result['order_id'] ?? null;
                $model->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Trade executed successfully',
                    'order_id' => $result['order_id'] ?? null,
                    'status' => $model->status
                ]);
            } else {
                $model->updateStatus('rejected', ['error' => $result['error']]);
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error('Trade execution failed', [
                'trade_id' => $tradeId,
                'error' => $e->getMessage()
            ]);

            $model->updateStatus('rejected', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel trade
     */
    public function postCancel($tradeId)
    {
        $model = $this->model->find($tradeId);

        if (!$model) {
            return response()->json(['success' => false, 'error' => 'Trade not found'], 404);
        }

        if (!in_array($model->status, ['open', 'pending'])) {
            return response()->json(['success' => false, 'error' => 'Trade cannot be cancelled'], 400);
        }

        try {
            $result = $this->tradeService->cancelTrade($model);

            if ($result['success']) {
                $model->updateStatus('cancelled', $result['exchange_response'] ?? null);
                return response()->json([
                    'success' => true,
                    'message' => 'Trade cancelled successfully'
                ]);
            } else {
                return response()->json(['success' => false, 'error' => $result['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error('Trade cancellation failed', [
                'trade_id' => $tradeId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete trade
     */
    public function getDelete($tradeId)
    {
        $model = $this->model->find($tradeId);

        if (!$model) {
            return redirect()->route($this->module('getData'))
                ->with('error', 'Trade not found');
        }

        // Only allow deletion of pending or cancelled trades
        if (!in_array($model->status, ['pending', 'cancelled', 'rejected'])) {
            return redirect()->route($this->module('getData'))
                ->with('error', 'Cannot delete active trade');
        }

        try {
            $model->delete();

            return redirect()->route($this->module('getData'))
                ->with('success', 'Trade deleted successfully');

        } catch (\Exception $e) {
            Log::error('Trade deletion failed', [
                'trade_id' => $tradeId,
                'error' => $e->getMessage()
            ]);

            return redirect()->route($this->module('getData'))
                ->with('error', 'Failed to delete trade: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete trades
     */
    public function postBulkDelete(Request $request)
    {
        $ids = explode(',', $request->ids);

        try {
            $deleted = $this->model->whereIn('trade_id', $ids)
                ->whereIn('status', ['pending', 'cancelled', 'rejected'])
                ->delete();

            return redirect()->route($this->module('getData'))
                ->with('success', "{$deleted} trades deleted successfully");

        } catch (\Exception $e) {
            Log::error('Bulk trade deletion failed', [
                'ids' => $ids,
                'error' => $e->getMessage()
            ]);

            return redirect()->route($this->module('getData'))
                ->with('error', 'Failed to delete trades: ' . $e->getMessage());
        }
    }

    /**
     * Get trade status from exchange
     */
    public function getStatus($tradeId)
    {
        $model = $this->model->find($tradeId);

        if (!$model) {
            return response()->json(['success' => false, 'error' => 'Trade not found'], 404);
        }

        try {
            $status = $this->tradeService->getTradeStatus($model);

            if ($status['success']) {
                $model->updateStatus($status['status'], $status['exchange_response'] ?? null);
                $model->price = $status['price'] ?? $model->price;
                $model->cost = $status['cost'] ?? $model->cost;
                $model->fee = $status['fee'] ?? $model->fee;
                $model->fee_currency = $status['fee_currency'] ?? $model->fee_currency;
                $model->save();

                return response()->json([
                    'success' => true,
                    'status' => $model->status,
                    'price' => $model->price,
                    'cost' => $model->cost,
                    'fee' => $model->fee,
                ]);
            } else {
                return response()->json(['success' => false, 'error' => $status['error']], 400);
            }

        } catch (\Exception $e) {
            Log::error('Trade status check failed', [
                'trade_id' => $tradeId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync all active trades with exchange
     */
    public function getSync()
    {
        try {
            $trades = $this->model->whereIn('status', ['open', 'filled'])->get();
            $synced = 0;

            foreach ($trades as $trade) {
                $status = $this->tradeService->getTradeStatus($trade);

                if ($status['success']) {
                    $trade->updateStatus($status['status'], $status['exchange_response'] ?? null);
                    $trade->price = $status['price'] ?? $trade->price;
                    $trade->cost = $status['cost'] ?? $trade->cost;
                    $trade->fee = $status['fee'] ?? $trade->fee;
                    $trade->fee_currency = $status['fee_currency'] ?? $trade->fee_currency;
                    $trade->save();
                    $synced++;
                }
            }

            return redirect()->route($this->module('getData'))
                ->with('success', "Synced {$synced} trades with exchange");

        } catch (\Exception $e) {
            Log::error('Trade sync failed', [
                'error' => $e->getMessage()
            ]);

            return redirect()->route($this->module('getData'))
                ->with('error', 'Failed to sync trades: ' . $e->getMessage());
        }
    }

    /**
     * Get trading pairs for a specific coin
     */
    private function getTradingPairsForCoin()
    {
        try {
            $coinSymbol = request('coin');

            if (!$coinSymbol) {
                return response()->json(['success' => false, 'error' => 'Coin symbol is required']);
            }

            // Get all available trading pairs
            $allPairs = $this->tradeService->getAvailableTradingPairs();

            // Filter pairs that contain the selected coin
            $filteredPairs = [];
            foreach ($allPairs as $symbol => $name) {
                if (str_contains(strtoupper($symbol), strtoupper($coinSymbol))) {
                    $filteredPairs[$symbol] = $name;
                }
            }

            return response()->json([
                'success' => true,
                'pairs' => $filteredPairs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get popular trading pairs for quick selection
     */
    private function getPopularTradingPairs()
    {
        $popularSymbols = [
            'BTC/USDT' => 'BTC/USDT - Bitcoin',
            'ETH/USDT' => 'ETH/USDT - Ethereum',
            'BNB/USDT' => 'BNB/USDT - Binance Coin',
            'ADA/USDT' => 'ADA/USDT - Cardano',
            'SOL/USDT' => 'SOL/USDT - Solana',
            'DOT/USDT' => 'DOT/USDT - Polkadot',
            'AVAX/USDT' => 'AVAX/USDT - Avalanche',
            'MATIC/USDT' => 'MATIC/USDT - Polygon',
            'LINK/USDT' => 'LINK/USDT - Chainlink',
            'UNI/USDT' => 'UNI/USDT - Uniswap',
        ];

        return $popularSymbols;
    }
}

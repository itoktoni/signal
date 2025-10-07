<?php

namespace App\Http\Controllers;

use App\Models\Trade;
use App\Models\Coin;
use App\Services\TradeService;
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

    public function __construct(Trade $model, TradeService $tradeService)
    {
        $this->model = $model;
        $this->tradeService = $tradeService;
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
     * Show the form for creating a new trade
     */
    public function getCreate()
    {
        // Get available coins for trading
        $coins = Coin::where('coin_watch', true)->pluck('coin_symbol', 'coin_code');

        // Get available trading pairs from exchange
        $tradingPairs = $this->tradeService->getAvailableTradingPairs();

        return $this->views($this->module(), [
            'coins' => $coins,
            'trading_pairs' => $tradingPairs,
        ]);
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
            'amount' => 'required|numeric|min:0.00000001',
            'price' => 'nullable|numeric|min:0',
            'trading_plan_id' => 'nullable|string',
            'analysis_method' => 'nullable|string',
            'analysis_result' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

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
}

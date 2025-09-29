<?php

namespace App\Http\Controllers;

use App\Models\Coin;
use App\Traits\ControllerHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Analysis\AnalysisServiceFactory;
use App\Analysis\ApiProviderManager;
use App\Settings\Settings;
use App\Enums\AnalysisType;
use ReflectionClass;

class CoinController extends Controller
{
    use ControllerHelper;

    protected $model;

    public function __construct(Coin $model)
    {
        $this->model = $model;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return redirect()->route($this->module('getData'));
    }

    public function getData()
    {
        $perPage = request('perpage', 10);
        $data = $this->model->filter(request())->orderBy('coin_watch', 'DESC')->paginate($perPage);
        $data->appends(request()->query());

        return $this->views($this->module(), [
            'data' => $data,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function getCreate()
    {
        return $this->views($this->module());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function postCreate()
    {
        return $this->create(request()->all());
    }

    /**
     * Display the specified resource.
     */
    public function getShow($code)
    {
        $model = $this->model->find($code);
        return $this->views($this->module());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function getWatch($code)
    {
        $model = $this->model->find($code);
        $model->coin_watch = !$model->coin_watch;
        $model->save();

        return redirect()->route($this->module('getData'))->with('success', 'updated successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function postUpdate(Request $request)
    {
        return $this->update($request->all(), $this->model);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function getDelete($code)
    {
        $model = $this->model->find($code);
        $model->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }

    public function postDelete($code)
    {
        $model = $this->model->find($code);
        $model->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }

    public function getProfile()
    {
        return view($this->module());
    }

    public function getSecurity()
    {
        return view($this->module());
    }

    public function postBulkDelete(Request $request)
    {
        $ids = explode(',', $request->ids);
        $this->model->whereIn('id', $ids)->delete();

        return redirect()->route($this->module('getData'))->with('success', 'deleted successfully');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function getUpdate($code)
    {
        // Get parameters
        $coinCode = request('coin_code', $code);
        $amount = max(1, floatval(request('amount', 100)));
        $analystMethod = request('analyst_method', 'ma_rsi_volume_atr_macd');

        // Find coin model
        $model = $this->model->find($coinCode);
        if (!$model) {
            return redirect()->route($this->module('getData'))
                ->with('error', "Coin '{$coinCode}' not found.");
        }

        // Initialize variables
        $cryptoAnalysis = [];
        $historicalData = [];

        try {
            // Create API manager and analysis service
            $apiManager = new ApiProviderManager(app(Settings::class));
            $analysisService = AnalysisServiceFactory::create($analystMethod, $apiManager);
            // Set timeframe based on analysis method
            $timeframe = ($analystMethod === 'support_resistance') ? '4h' : '1h';

            // Perform analysis
            $result = $analysisService->analyze($model->coin_code, $amount, $timeframe);
            $cryptoAnalysis = $this->convertAnalysisResult($result, $model->coin_code);

            // Get historical data for chart
            if ($analystMethod === 'support_resistance' || $analystMethod === 'simple_ma') {
                $historicalData = $apiManager->getHistoricalData($model->coin_code, '4h', 100);
            }

            // Get current prices from both APIs
            $currentPrices = $apiManager->getPricesFromBothAPIs($model->coin_code);

        } catch (\Exception $e) {
            Log::error('Analysis failed', [
                'coin' => $model->coin_code,
                'method' => $analystMethod,
                'error' => $e->getMessage()
            ]);

            $cryptoAnalysis = [
                'error' => 'Analysis failed: ' . $e->getMessage(),
                'symbol' => $model->coin_code,
                'signal' => 'NEUTRAL',
                'confidence' => 0
            ];

            $currentPrices = [
                'binance' => null,
                'coingecko' => null,
                'symbol' => $model->coin_code
            ];
        }

        // Get coin options for dropdown
        $coin = Coin::getOptions('coin_code', 'coin_code');

        // Get analysis methods and current API provider info
        $analysisMethods = \App\Analysis\AnalysisServiceFactory::getAvailableMethods();
        $currentProvider = null;
        try {
            $providerAnalysisService = \App\Analysis\AnalysisServiceFactory::create($analystMethod, $apiManager);
            $reflection = new ReflectionClass($providerAnalysisService);
            $property = $reflection->getProperty('apiProvider');
            $property->setAccessible(true);
            $currentProvider = $property->getValue($providerAnalysisService);
        } catch (\Exception $e) {
            $currentProvider = null;
        }

        // Process analysis data for display
        $signal = $cryptoAnalysis['signal'] ?? 'NEUTRAL';
        $confidence = $cryptoAnalysis['confidence'] ?? 0;
        $rrRatio = $cryptoAnalysis['risk_reward'] ?? 0;
        $entry = $cryptoAnalysis['entry'] ?? 0;
        $stopLoss = $cryptoAnalysis['stop_loss'] ?? 0;
        $takeProfit = $cryptoAnalysis['take_profit'] ?? 0;
        $fee = $cryptoAnalysis['fee'] ?? 0;
        $potentialProfit = $cryptoAnalysis['potential_profit'] ?? 0;
        $potentialLoss = $cryptoAnalysis['potential_loss'] ?? 0;
        $title = $cryptoAnalysis['title'] ?? 'Analysis';

        // Calculate IDR values
        $exchangeRate = 16000; // Default exchange rate
        $entryUsd = $entry;
        $entryIdr = $entry * $exchangeRate;
        $stopLossUsd = $stopLoss;
        $stopLossIdr = $stopLoss * $exchangeRate;
        $takeProfitUsd = $takeProfit;
        $takeProfitIdr = $takeProfit * $exchangeRate;
        $feeUsd = $fee;
        $feeIdr = $fee * $exchangeRate;
        $potentialProfitUsd = $potentialProfit;
        $potentialProfitIdr = $potentialProfit * $exchangeRate;
        $potentialLossUsd = abs($potentialLoss);
        $potentialLossIdr = abs($potentialLoss) * $exchangeRate;

        $signalClass = $signal === 'BUY' ? 'buy' : ($signal === 'SELL' ? 'sell' : 'neutral');
        $signalText = $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');

        // Process indicators for display
        $hasIndicators = false;
        $indicators = [];
        if (isset($cryptoAnalysis['indicators']) && is_array($cryptoAnalysis['indicators'])) {
            $indicators = $cryptoAnalysis['indicators'];
            $hasIndicators = !empty($indicators);
        }

        return $this->views($this->module(), $this->share([
            'model' => $model,
            'coin' => $coin->toArray(),
            'crypto_analysis' => $cryptoAnalysis,
            'amount' => $amount,
            'analyst_service' => $analysisService,
            'analyst_method' => $analystMethod,
            'historical_data' => $historicalData,
            'current_prices' => $currentPrices,
            'analysis_methods' => $analysisMethods,
            'current_provider' => $currentProvider,
            'signal' => $signal,
            'confidence' => $confidence,
            'rrRatio' => $rrRatio,
            'entry' => $entry,
            'stopLoss' => $stopLoss,
            'takeProfit' => $takeProfit,
            'fee' => $fee,
            'potentialProfit' => $potentialProfit,
            'potentialLoss' => $potentialLoss,
            'title' => $title,
            'exchangeRate' => $exchangeRate,
            'entryUsd' => $entryUsd,
            'entryIdr' => $entryIdr,
            'stopLossUsd' => $stopLossUsd,
            'stopLossIdr' => $stopLossIdr,
            'takeProfitUsd' => $takeProfitUsd,
            'takeProfitIdr' => $takeProfitIdr,
            'feeUsd' => $feeUsd,
            'feeIdr' => $feeIdr,
            'potentialProfitUsd' => $potentialProfitUsd,
            'potentialProfitIdr' => $potentialProfitIdr,
            'potentialLossUsd' => $potentialLossUsd,
            'potentialLossIdr' => $potentialLossIdr,
            'signalClass' => $signalClass,
            'signalText' => $signalText,
            'hasIndicators' => $hasIndicators,
            'indicators' => $indicators,
        ]));
    }

    /**
     * Convert analysis result to view format
     */
    private function convertAnalysisResult($result, $symbol)
    {
        // Normalize signal
        $signal = strtoupper($result->signal ?? 'NEUTRAL');
        if ($signal === 'LONG') $signal = 'BUY';
        elseif ($signal === 'SHORT') $signal = 'SELL';
        elseif ($signal === 'HOLD') $signal = 'NEUTRAL';

        return [
            'symbol' => $symbol,
            'title' => $result->title ?? 'Analysis',
            'description' => $result->description ?? '',
            'signal' => $signal,
            'confidence' => (float) ($result->confidence ?? 50),
            'risk_reward' => $result->risk_reward ?? '1:1',
            'entry' => (float) ($result->entry ?? 0),
            'stop_loss' => (float) ($result->stop_loss ?? 0),
            'take_profit' => (float) ($result->take_profit ?? 0),
            'indicators' => array_map(function($value) {
                return is_numeric($value) ? (float) $value : $value;
            }, (array) ($result->indicators ?? [])),
            'notes' => $result->notes ?? '',
            'last_updated' => now()->format('Y-m-d H:i:s'),
            'analysis_type' => $result->title ?? 'Analysis'
        ];
    }


}
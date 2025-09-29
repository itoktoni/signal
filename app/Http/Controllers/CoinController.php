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
        $code = request('coin_code', $code);

        // Validate that the coin code exists
        $model = $this->model->find($code);

        // If coin doesn't exist in database, redirect with error
        if (!$model) {
            return redirect()->route($this->module('getData'))
                ->with('error', "Coin '{$code}' not found. Please select a valid cryptocurrency.");
        }

        // Skip API provider validation - allow any symbol for analysis

        // Get trading amount from query parameter (default to 100)
        $amount = floatval(request('amount', 100));

        // Get analysis method from query parameter (default to MA analysis)
        $analystMethod = request('analyst_method', 'ma_rsi_volume_atr_macd');

        // Validate amount
        if ($amount <= 0) {
            $amount = 100;
        }

        // Perform analysis using selected method
        try {
            // Create API provider manager
            $settings = app(Settings::class);
            $apiManager = new ApiProviderManager($settings);

            // Use selected analysis service with dynamic amount
            $analysisService = AnalysisServiceFactory::create($analystMethod, $apiManager);
            $result = $analysisService->analyze($model->coin_code ?? 'BTCUSDT', $amount);

            // Convert the new result structure to match the existing view expectations
            $cryptoAnalysis = $this->convertAnalysisResult($result, $model->coin_code ?? 'BTCUSDT');

            // Fetch historical data for chart if support resistance analysis
            $historicalData = [];
            if ($analystMethod === 'support_resistance') {
                try {
                    $historicalData = $apiManager->getHistoricalData($model->coin_code ?? 'BTCUSDT', '1h', 100);
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch historical data for chart', [
                        'coin_code' => $model->coin_code ?? 'Unknown',
                        'error' => $e->getMessage()
                    ]);
                    $historicalData = [];
                }
            }

        } catch (\Exception $e) {
            // Log the error and return error response
            Log::error('Crypto analysis failed', [
                'coin_code' => $model->coin_code ?? 'Unknown',
                'analyst_method' => $analystMethod,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            $cryptoAnalysis = [
                'error' => $e->getMessage(),
                'symbol' => $model->coin_code ?? 'Unknown',
                'current_price' => null,
                'analysis' => null
            ];
            $historicalData = [];
        }

        $coin = Coin::getOptions('coin_code', 'coin_code');

        return $this->views($this->module(), $this->share([
            'model' => $model,
            'coin' => $coin->toArray(),
            'crypto_analysis' => $cryptoAnalysis,
            'amount' => $amount,
            'analyst_method' => $analystMethod,
            'historical_data' => $historicalData,
        ]));
    }

    /**
     * Convert new analysis result structure to match existing view expectations
     */
    private function convertAnalysisResult($result, $symbol)
    {
        // Extract signal from the new result structure
        $signal = strtoupper($result->signal); // Convert 'long'/'short'/'hold' to 'BUY'/'SELL'/'NEUTRAL'
        if ($signal === 'LONG') {
            $signal = 'BUY';
        } elseif ($signal === 'SHORT') {
            $signal = 'SELL';
        } elseif ($signal === 'HOLD') {
            $signal = 'NEUTRAL';
        }

        // Extract indicators
        $indicators = [];

        if (isset($result->indicators) && is_array($result->indicators)) {
            $indicators = $result->indicators;
        }

        return [
            'symbol' => $symbol,
            'title' => $result->title ?? 'Analysis',
            'description' => $result->description ?? '',
            'signal' => $signal,
            'confidence' => safeNumericValue($result, 'confidence', 50),
            'risk_reward' => safeValue($result, 'risk_reward'),
            'entry' => safeNumericValue($result, 'entry'),
            'stop_loss' => safeNumericValue($result, 'stop_loss'),
            'take_profit' => safeNumericValue($result, 'take_profit'),
            'fee' => safeNumericValue($result, 'fee'),
            'potential_profit' => safeNumericValue($result, 'potential_profit'),
            'potential_loss' => safeNumericValue($result, 'potential_loss'),
            'indicators' => $indicators,
            'notes' => safeValue($result, 'notes', ''),
            'last_updated' => now()->format('Y-m-d H:i:s'),
            'analysis_type' => safeValue($result, 'title', 'Analysis')
        ];
    }


}
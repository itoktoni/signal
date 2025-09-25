<?php

namespace App\Http\Controllers;

use App\Models\Coin;
use App\Traits\ControllerHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Analysis\AnalysisServiceFactory;
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

        $model = $this->model->find($code);

        // Available analyst methods from the factory
        $analystMethods = AnalysisServiceFactory::getAvailableMethods();

        // Get analysis method from query parameter
        $analystMethod = request('analyst', 'sniper');

        // Get trading amount from query parameter (default to 1000)
        $amount = floatval(request('amount', 1000));

        // Validate analyst method
        if (!array_key_exists($analystMethod, $analystMethods)) {
            $analystMethod = AnalysisType::SNIPER;
        }

        // Validate amount
        if ($amount <= 0) {
            $amount = 1000;
        }

        // Perform analysis using the new service architecture
        try {
            if (in_array($analystMethod, [AnalysisType::SNIPER, AnalysisType::DYNAMIC_RR])) {
                // Use new analysis services with dynamic amount
                $analysisService = AnalysisServiceFactory::create($analystMethod);
                $result = $analysisService->analyze($model->coin_code ?? 'BTCUSDT', $amount);

                // Convert the new result structure to match the existing view expectations
                $cryptoAnalysis = $this->convertAnalysisResult($result, $model->coin_code ?? 'BTCUSDT');
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
        }

        $coin = Coin::getOptions('coin_code', 'coin_code');

        return $this->views($this->module(), $this->share([
            'model' => $model,
            'coin' => $coin->toArray(),
            'crypto_analysis' => $cryptoAnalysis,
            'analyst_method' => $analystMethod,
            'analyst_methods' => $analystMethods,
            'amount' => $amount,
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

        // Calculate RR ratio from the new result structure
        $rrRatio = $result->risk_reward ?? 0;

        // Extract entry, stop loss, and take profit with both USD and Rupiah
        $entry = $result->entry ?? ['usd' => 0, 'rupiah' => 0];
        $stopLoss = $result->stop_loss ?? ['usd' => 0, 'rupiah' => 0];
        $takeProfit = $result->take_profit ?? ['usd' => 0, 'rupiah' => 0];

        // Extract and restructure fee information for the view
        $feeData = $result->fee ?? null;
        $fee = ['usd' => 0, 'rupiah' => 0];
        if ($feeData && isset($feeData['total'])) {
            $fee = [
                'usd' => $feeData['total'],
                'rupiah' => $feeData['total'] * 16000, // Convert USD to Rupiah
                'breakdown' => $feeData, // Keep original breakdown for reference
                'description' => $feeData['description'] ?? 'Biaya transaksi dan pajak'
            ];
        }

        // Extract potential profit/loss information
        // These are now returned as formatted arrays with USD and Rupiah values
        $potentialProfit = $result->potential_profit ?? ['usd' => 0, 'rupiah' => 0];
        $potentialLoss = $result->potential_loss ?? ['usd' => 0, 'rupiah' => 0];

        // Only include indicators if they exist in the result
        $indicators = [];
        if (isset($result->indicators) && is_array($result->indicators)) {
            $indicators = $result->indicators;
        }

        return [
            'symbol' => $symbol,
            'current_price' => $entry['usd'] ?? 0, // Use entry price as current price for compatibility
            'analysis' => [
                'signal' => $signal,
                'confidence' => $result->confidence ?? 50,
                'entry' => $entry,
                'stop_loss' => $stopLoss,
                'take_profit' => $takeProfit,
                'rr_ratio' => $rrRatio,
                'indicators' => $indicators
            ],
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'fee' => $fee,
            'potential_profit' => $potentialProfit,
            'potential_loss' => $potentialLoss,
            'last_updated' => now()->format('Y-m-d H:i:s'),
            'analysis_type' => $result->title ?? 'Analysis'
        ];
    }

    // ... existing methods ...
}
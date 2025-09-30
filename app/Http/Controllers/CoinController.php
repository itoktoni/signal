<?php

namespace App\Http\Controllers;

use App\Models\Coin;
use App\Traits\ControllerHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Analysis\AnalysisServiceFactory;
use App\Analysis\ApiProviderManager;
use App\Analysis\Providers\BinanceProvider;
use App\Analysis\Providers\CoingeckoProvider;
use App\Analysis\TestAnalysis;
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
        $analystMethod = request('analyst_method', 'simple_ma');
        $timeframe = request('timeframe', '4h');

        // Find coin model
        $model = $this->model->find($coinCode);
        if (!$model) {
            return redirect()->route($this->module('getData'))
                ->with('error', "Coin '{$coinCode}' not found.");
        }

        // Initialize variables
        $result = false;

        try {

            $defaultApi = env('DEFAULT_API_PROVIDER', 'binance');
            if($defaultApi == 'binance')
            {
                $provider = new BinanceProvider();
            }
            else
            {
                $provider = new CoingeckoProvider();
            }

            $analysis = new TestAnalysis($provider);
            $result = $analysis->analyze('bitcoin', 100, '1h');

        } catch (\Exception $e) {
            Log::error('Analysis failed', [
                'coin' => $model->coin_code,
                'method' => $analystMethod,
                'error' => $e->getMessage()
            ]);

            $result = $this->getError();
        }

        // Get coin options for dropdown
        $coin = Coin::getOptions('coin_code', 'coin_code');

        return $this->views($this->module(), $this->share([
            'model' => $model,
            'coin' => $coin->toArray(),
            'amount' => $amount,
            'analyst_method' => $analystMethod,
            'timeframe' => $timeframe,
            'current_provider' => $provider,
            'result' => $result,
        ]));
    }

    private function getError()
    {
        return (Object)[
            'title' => 'Analysis Error',
            'description' => [
                'title' => 'Analysis Error',
                'error' => 'Analysis failed',
                'message' => $e->getMessage(),
                'details' => 'Please check your internet connection and API availability'
            ],
            'signal' => 'NEUTRAL',
            'confidence' => 0,
            'entry' => 0,
            'price' => 0,
            'stop_loss' => 0,
            'take_profit' => 0,
            'risk_reward' => '1:1',
            'indicators' => [],
            'notes' => [
                'analysis_notes' => 'Analysis failed: ' . $e->getMessage(),
                'signal_strength' => 'Unknown - analysis failed',
                'market_condition' => 'Error state',
                'risk_assessment' => 'Cannot assess - analysis failed',
                'execution_tips' => 'Please try again later or check API connectivity'
            ],
        ];
    }

}
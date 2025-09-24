<x-layout>

    <div class="row">
        <!-- Crypto Analysis Section -->
        <div class="col-12">
            <x-card title="🔍 {{ $analyst_method === 'sniper_entry' ? 'Sniper Entry' : 'Basic' }} Analysis - {{ $model->coin_code ?? 'Unknown' }}">
                @if(isset($crypto_analysis) && !isset($crypto_analysis['error']))
                    <div class="analysis-results">
                        <div class="row">
                            <div class="col-6">
                                <div class="info-card">
                                    <h4>📊 Market Data</h4>
                                    <p><strong>Symbol:</strong> {{ $crypto_analysis['symbol'] }}</p>
                                    <p><strong>Current Price:</strong> ${{ number_format($crypto_analysis['current_price'], 8) }}</p>
                                    <p><strong>Last Updated:</strong> {{ $crypto_analysis['last_updated'] }}</p>
                                    <p><strong>Analysis Type:</strong>
                                        <span class="badge {{ $analyst_method === 'sniper_entry' ? 'badge-success' : 'badge-info' }}">
                                            {{ $analyst_method === 'sniper_entry' ? 'Sniper Entry' : 'Basic' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-card">
                                    <h4>🎯 Trading Signal</h4>
                                    @php
                                        $signal = $crypto_analysis['analysis']['signal'] ?? 'NEUTRAL';
                                        $confidence = $crypto_analysis['analysis']['confidence'] ?? 0;
                                        $signalClass = $signal === 'BUY' ? 'success' : ($signal === 'SELL' ? 'danger' : 'warning');
                                    @endphp
                                    <p><strong>Signal:</strong>
                                        <span class="badge badge-{{ $signalClass }}">
                                            {{ $signal }}
                                        </span>
                                    </p>
                                    <p><strong>Confidence:</strong> {{ $confidence }}%</p>
                                    <p><strong>Risk-Reward Ratio:</strong> {{ $crypto_analysis['analysis']['rr_ratio'] ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📈 Technical Levels</h4>
                                    <div class="row">
                                        <div class="col-3">
                                            <p><strong>Entry:</strong></p>
                                            <span class="price-value">${{ number_format($crypto_analysis['analysis']['entry'], 3) }}</span>
                                        </div>
                                        <div class="col-3">
                                            <p><strong>Stop Loss:</strong></p>
                                            <span class="price-value text-danger">
                                                ${{ number_format($crypto_analysis['analysis']['stop_loss'], 3) }}
                                            </span>
                                        </div>
                                        <div class="col-3">
                                            <p><strong>Take Profit:</strong></p>
                                            <span class="price-value text-success">
                                                ${{ number_format($crypto_analysis['analysis']['take_profit'], 3) }}
                                            </span>
                                        </div>
                                        <div class="col-3">
                                            <p><strong>Potential:</strong></p>
                                            <span class="price-value">
                                                {{ $crypto_analysis['analysis']['rr_ratio'] ? $crypto_analysis['analysis']['rr_ratio'] . ':1 RR' : 'N/A' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📊 Technical Indicators</h4>
                                    <div class="row">
                                        @if($analyst_method === 'sniper_entry')
                                            <!-- Sniper Entry Indicators -->
                                            <div class="col-2">
                                                <p><strong>EMA 9:</strong></p>
                                                <span>${{ number_format($crypto_analysis['analysis']['indicators']['ema9'], 3) }}</span>
                                            </div>
                                            <div class="col-2">
                                                <p><strong>EMA 21:</strong></p>
                                                <span>${{ number_format($crypto_analysis['analysis']['indicators']['ema21'], 3) }}</span>
                                            </div>
                                            <div class="col-2">
                                                <p><strong>EMA 50:</strong></p>
                                                <span>${{ number_format($crypto_analysis['analysis']['indicators']['ema50'], 3) }}</span>
                                            </div>
                                            <div class="col-2">
                                                <p><strong>RSI 14:</strong></p>
                                                <span>{{ $crypto_analysis['analysis']['indicators']['rsi'] }}</span>
                                            </div>
                                            <div class="col-2">
                                                <p><strong>Support:</strong></p>
                                                <span class="text-success">${{ number_format($crypto_analysis['analysis']['indicators']['support'], 3) }}</span>
                                            </div>
                                            <div class="col-2">
                                                <p><strong>Resistance:</strong></p>
                                                <span class="text-danger">${{ number_format($crypto_analysis['analysis']['indicators']['resistance'], 3) }}</span>
                                            </div>
                                        @else
                                            <!-- Basic Analysis Indicators -->
                                            <div class="col-3">
                                                <p><strong>EMA 20:</strong></p>
                                                <span>${{ number_format($crypto_analysis['analysis']['indicators']['ema20'], 3) }}</span>
                                            </div>
                                            <div class="col-3">
                                                <p><strong>RSI 14:</strong></p>
                                                <span>{{ $crypto_analysis['analysis']['indicators']['rsi'] }}</span>
                                            </div>
                                            <div class="col-3">
                                                <p><strong>Support:</strong></p>
                                                <span class="text-success">${{ number_format($crypto_analysis['analysis']['indicators']['support'], 3) }}</span>
                                            </div>
                                            <div class="col-3">
                                                <p><strong>Resistance:</strong></p>
                                                <span class="text-danger">${{ number_format($crypto_analysis['analysis']['indicators']['resistance'], 3) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Analysis Error:</strong> {{ $crypto_analysis['error'] ?? 'Unable to perform analysis' }}
                    </div>
                @endif
            </x-card>
        </div>

        <!-- Analysis Form Section -->
        <div class="col-12">
            <x-card title="🎯 Analysis Configuration">
                <x-form method="GET" action="{{ route(module('getUpdate'), $model) }}">
                    <x-select searchable name="coin_code" :value="$model->coin_code" :options="$coin" label="Analysis Method" />
                    <x-select name="analyst" :options="['sniper_entry' => 'Sniper Entry Analysis (Advanced)', 'basic' => 'Basic Analysis (Simple)']" label="Analysis Method" :value="request('analyst', 'sniper_entry')" hint="Sniper Entry uses multiple EMAs and 4h timeframe for precise entries"/>

                    <x-footer>
                        <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                        <x-button type="submit" class="primary">Analyze</x-button>
                    </x-footer>

                </x-form>
            </x-card>
        </div>
    </div>

    <style>
    .analysis-results {
        margin-bottom: 20px;
    }
    .info-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .info-card h4 {
        margin-top: 0;
        color: #495057;
        border-bottom: 2px solid #007bff;
        padding-bottom: 8px;
    }
    .price-value {
        font-size: 16px;
        font-weight: bold;
        display: block;
        margin-top: 5px;
    }
    .badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
    }
    .badge-success {
        background-color: #28a745;
        color: white;
    }
    .badge-danger {
        background-color: #dc3545;
        color: white;
    }
    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }
    .badge-info {
        background-color: #17a2b8;
        color: white;
    }
    .alert {
        padding: 15px;
        border: 1px solid transparent;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    .alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeaa7;
    }
    </style>
</x-layout>

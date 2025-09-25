<x-layout>
    <style>
        :root {
            --crypto-primary: #2563eb;
            --crypto-secondary: #7c3aed;
            --crypto-success: #10b981;
            --crypto-danger: #ef4444;
            --crypto-warning: #f59e0b;
            --crypto-dark: #1e293b;
            --crypto-light: #f8fafc;
            --crypto-card-bg: #ffffff;
            --crypto-border: #e2e8f0;
            --crypto-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .crypto-dashboard {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .crypto-header {
            background: linear-gradient(135deg, var(--crypto-primary) 0%, var(--crypto-secondary) 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--crypto-shadow);
        }

        .crypto-card {
            background: var(--crypto-card-bg);
            border-radius: 16px;
            box-shadow: var(--crypto-shadow);
            border: 1px solid var(--crypto-border);
            margin: 2rem;
        }

        .crypto-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .crypto-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--crypto-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .crypto-card-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--crypto-dark);
            margin: 0;
        }

        .crypto-card-body {
            padding: 24px;
        }

        .signal-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1.7rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .signal-badge.buy {
            background-color: #dcfce7;
            color: var(--crypto-success);
        }

        .signal-badge.sell {
            background-color: #fee2e2;
            color: var(--crypto-danger);
        }

        .signal-badge.neutral {
            background-color: #fef3c7;
            color: var(--crypto-warning);
        }

        .crypto-metric {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .crypto-metric-label {
            font-size: 1.7rem;
            color: #64748b;
            font-weight: 500;
        }

        .crypto-metric-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--crypto-dark);
        }

        .crypto-price {
            font-size: 1.7rem;
            font-weight: 700;
        }

        .crypto-grid {
            display: grid;
            gap: 24px;
        }

        .crypto-grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .crypto-grid-cols-3 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .crypto-section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--crypto-dark);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--crypto-primary);
            display: inline-block;
        }

        .crypto-data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .crypto-data-item {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .crypto-data-label {
            font-size: 1.5rem;
            color: #64748b;
            margin-bottom: 4px;
        }

        .crypto-data-value {
            font-size: 1.525rem;
            font-weight: 600;
            color: var(--crypto-dark);
        }

        .crypto-price-usd {
            color: var(--crypto-dark);
            font-weight: 700;
        }

        .crypto-price-idr {
            color: #64748b;
            font-size: 1.5rem;
        }

        .crypto-indicator-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
        }

        .crypto-indicator-card {
            background: #f8fafc;
            border: 1px solid var(--crypto-border);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .crypto-indicator-label {
            font-size: 1.5rem;
            color: #64748b;
            margin-bottom: 8px;
        }

        .crypto-indicator-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--crypto-dark);
        }

        .crypto-profit-positive {
            color: var(--crypto-success);
        }

        .crypto-profit-negative {
            color: var(--crypto-danger);
        }

        .crypto-conclusion-card {
            background: #eff6ff;
            border-left: 4px solid var(--crypto-primary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .crypto-conclusion-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--crypto-dark);
            margin-bottom: 12px;
        }

        .crypto-conclusion-content {
            color: #334155;
            line-height: 1.6;
        }

        .crypto-alert {
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .crypto-alert-success {
            background-color: #dcfce7;
            border-left: 4px solid var(--crypto-success);
        }

        .crypto-alert-warning {
            background-color: #fef3c7;
            border-left: 4px solid var(--crypto-warning);
        }

        .crypto-alert-danger {
            background-color: #fee2e2;
            border-left: 4px solid var(--crypto-danger);
        }

        .crypto-alert-info {
            background-color: #dbeafe;
            border-left: 4px solid var(--crypto-primary);
        }


        @media (max-width: 768px) {
            .crypto-grid-cols-2,
            .crypto-grid-cols-3 {
                grid-template-columns: 1fr;
            }

            .crypto-header {
                padding: 16px;
            }

            .crypto-card-body {
                padding: 16px;
            }
        }
    </style>

    <div class="crypto-dashboard">
        <!-- Crypto Analysis Header -->
        <div class="crypto-header">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <h1 class="mb-2">
                        <i class="bi bi-graph-up"></i>
                        {{ $crypto_analysis['analysis_type'] ?? (AnalysisType::{strtoupper($analyst_method)}()->getAnalysisDescription()) ?? 'Basic Analysis' }}
                    </h1>
                    <p class="mb-0">
                        <i class="bi bi-currency-bitcoin"></i>
                        {{ $model->coin_code ?? 'Tidak Diketahui' }}
                        <span class="mx-2">•</span>
                        Last Updated: {{ $crypto_analysis['last_updated'] ?? now()->format('Y-m-d H:i:s') }}
                    </p>
                </div>
                <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
                    @if(isset($crypto_analysis) && !isset($crypto_analysis['error']))
                        @php
                            $signal = $crypto_analysis['signal'] ?? 'NEUTRAL';
                            $signalClass = $signal === 'BUY' ? 'buy' : ($signal === 'SELL' ? 'sell' : 'neutral');
                            $signalText = $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');
                        @endphp
                        <span class="signal-badge {{ $signalClass }}">
                            {{ $signalText }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Crypto Analysis Content -->
        <div class="row">
            <div class="col-12">
                <x-card title="📊 Crypto Analysis Results">
                    @if(isset($crypto_analysis) && !isset($crypto_analysis['error']))
                        @php
                            // Standardized analysis result structure from all analysis services
                            $signal = $crypto_analysis['signal'] ?? 'NEUTRAL';
                            $confidence = $crypto_analysis['confidence'] ?? 0;
                            $rrRatio = $crypto_analysis['risk_reward'] ?? 0;
                            // Updated to match the new standardized format with separate _usd and _idr fields
                            $entryUsd = $crypto_analysis['entry_usd'] ?? 0;
                            $entryIdr = $crypto_analysis['entry_idr'] ?? 0;
                            $stopLossUsd = $crypto_analysis['stop_loss_usd'] ?? 0;
                            $stopLossIdr = $crypto_analysis['stop_loss_idr'] ?? 0;
                            $takeProfitUsd = $crypto_analysis['take_profit_usd'] ?? 0;
                            $takeProfitIdr = $crypto_analysis['take_profit_idr'] ?? 0;
                            $title = $crypto_analysis['title'] ?? 'Analysis';
                            $feeUsd = $crypto_analysis['fee_usd'] ?? 0;
                            $feeIdr = $crypto_analysis['fee_idr'] ?? 0;
                            $potentialProfitUsd = $crypto_analysis['potential_profit_usd'] ?? 0;
                            $potentialProfitIdr = $crypto_analysis['potential_profit_idr'] ?? 0;
                            $potentialLossUsd = $crypto_analysis['potential_loss_usd'] ?? 0;
                            $potentialLossIdr = $crypto_analysis['potential_loss_idr'] ?? 0;

                            $signalClass = $signal === 'BUY' ? 'buy' : ($signal === 'SELL' ? 'sell' : 'neutral');
                            $signalText = $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');
                        @endphp

                        <!-- Key Metrics Overview -->
                        <div class="crypto-grid crypto-grid-cols-3 mb-4">
                            <div class="crypto-card">
                                <div class="crypto-card-body">
                                    <div class="crypto-metric">
                                        <div class="crypto-metric-label">Confidence Level</div>
                                        <div class="crypto-metric-value">{{ $confidence }}%</div>
                                        <div class="mt-2">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-{{ $confidence >= 70 ? 'success' : ($confidence >= 50 ? 'warning' : 'danger') }}"
                                                     role="progressbar"
                                                     style="width: {{ $confidence }}%"
                                                     aria-valuenow="{{ $confidence }}"
                                                     aria-valuemin="0"
                                                     aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="crypto-card">
                                <div class="crypto-card-body">
                                    <div class="crypto-metric">
                                        <div class="crypto-metric-label">Risk-Reward Ratio</div>
                                        <div class="crypto-metric-value">{{ $rrRatio }}:1</div>
                                        <div class="mt-2">
                                            <span class="badge bg-{{ $rrRatio >= 2 ? 'success' : 'warning' }}">
                                                {{ $rrRatio >= 2 ? 'GOOD' : 'MODERATE' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="crypto-card">
                                <div class="crypto-card-body">
                                    <div class="crypto-metric">
                                        <div class="crypto-metric-label">Current Price</div>
                                        <div class="crypto-price">${{ number_format($entryUsd, 3) }}</div>
                                        <div class="crypto-price-idr">Rp {{ number_format($entryIdr, 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trading Levels -->
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-bar-chart-steps"></i> Trading Levels
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                <div class="crypto-data-grid">
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Entry Point</div>
                                        <div class="crypto-data-value crypto-price-usd">${{ number_format($entryUsd, 3) }}</div>
                                        <div class="crypto-price-idr">Rp {{ number_format($entryIdr, 0) }}</div>
                                    </div>
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Stop Loss</div>
                                        <div class="crypto-data-value text-danger">${{ number_format($stopLossUsd, 3) }}</div>
                                        <div class="crypto-price-idr text-danger">Rp {{ number_format($stopLossIdr, 0) }}</div>
                                    </div>
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Take Profit</div>
                                        <div class="crypto-data-value text-success">${{ number_format($takeProfitUsd, 3) }}</div>
                                        <div class="crypto-price-idr text-success">Rp {{ number_format($takeProfitIdr, 0) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Information -->
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-currency-dollar"></i> Fee Information
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                <div class="crypto-data-grid">
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Total Fee (USD)</div>
                                        <div class="crypto-data-value">${{ number_format($feeUsd, 4) }}</div>
                                        @if($amount > 0)
                                            <div class="crypto-price-idr">({{ number_format(($feeUsd / $amount) * 100, 2) }}% of trade)</div>
                                        @endif
                                    </div>
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Total Fee (IDR)</div>
                                        <div class="crypto-data-value">Rp {{ number_format($feeIdr, 0) }}</div>
                                        @if($amount > 0)
                                            <div class="crypto-price-idr">({{ number_format(($feeIdr / ($amount * 16000)) * 100, 2) }}% of trade)</div>
                                        @endif
                                    </div>
                                </div>
                                @if(isset($crypto_analysis['fee']) && is_array($crypto_analysis['fee']) && isset($crypto_analysis['fee']['description']))
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <small class="text-muted">{{ $crypto_analysis['fee']['description'] }}</small>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Profit/Loss Potential -->
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-graph-up-arrow"></i> Profit & Loss Potential
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                <div class="crypto-data-grid">
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Potential Profit</div>
                                        <div class="crypto-data-value crypto-profit-positive">${{ number_format($potentialProfitUsd, 2) }}</div>
                                        <div class="crypto-price-idr crypto-profit-positive">Rp {{ number_format($potentialProfitIdr, 0) }}</div>
                                        @if($amount > 0)
                                            <div class="crypto-price-idr">({{ number_format(($potentialProfitUsd / $amount) * 100, 1) }}%)</div>
                                        @endif
                                    </div>
                                    <div class="crypto-data-item">
                                        <div class="crypto-data-label">Potential Loss</div>
                                        @php
                                            $displayLossUsd = abs($potentialLossUsd);
                                            $displayLossIdr = abs($potentialLossIdr);
                                        @endphp
                                        <div class="crypto-data-value crypto-profit-negative">${{ number_format($displayLossUsd, 2) }}</div>
                                        <div class="crypto-price-idr crypto-profit-negative">Rp {{ number_format($displayLossIdr, 0) }}</div>
                                        @if($amount > 0)
                                            <div class="crypto-price-idr">({{ number_format(($displayLossUsd / $amount) * 100, 1) }}%)</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Technical Indicators -->
                        @php
                            $hasIndicators = false;
                            if (isset($crypto_analysis['indicators']) && is_array($crypto_analysis['indicators'])) {
                                $indicators = $crypto_analysis['indicators'];
                                $hasIndicators = !empty($indicators);
                            }

                            $currentConfig = $crypto_analysis['indicator_config'] ?? [];
                        @endphp

                        @if($hasIndicators)
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-activity"></i> Technical Indicators
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                <div class="crypto-indicator-grid">
                                    @foreach($currentConfig as $key => $config)
                                        @if(isset($indicators[$key]))
                                        <div class="crypto-indicator-card">
                                            <div class="crypto-indicator-label">{{ $config['label'] }}</div>
                                            <div class="crypto-indicator-value">
                                                @if($config['format'] === 'price')
                                                    ${{ number_format($indicators[$key], 3) }}
                                                @else
                                                    {{ $indicators[$key] }}
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Analysis Conclusion -->
                        @if(isset($crypto_analysis['conclusion']) && !empty($crypto_analysis['conclusion']))
                        <div class="crypto-card">
                            <div class="crypto-card-header">
                                <h3 class="crypto-card-title">
                                    <i class="bi bi-clipboard-data"></i> Analysis Conclusion
                                </h3>
                            </div>
                            <div class="crypto-card-body">
                                @if(isset($crypto_analysis['conclusion']['recommendations']['title']))
                                <div class="crypto-conclusion-card">
                                    <h4 class="crypto-conclusion-title">{{ $crypto_analysis['conclusion']['recommendations']['title'] }}</h4>
                                    <div class="crypto-conclusion-content">
                                        @if(isset($crypto_analysis['conclusion']['recommendations']['description']))
                                            <p>{{ $crypto_analysis['conclusion']['recommendations']['description'] }}</p>
                                        @endif

                                        @if(isset($crypto_analysis['conclusion']['recommendations']['trading_advice']))
                                            <ul class="mt-3">
                                                @foreach($crypto_analysis['conclusion']['recommendations']['trading_advice'] as $advice)
                                                    <li>{{ $advice }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                                @endif

                                @if(isset($crypto_analysis['conclusion']['method_descriptions']['description']))
                                <div class="crypto-alert crypto-alert-info">
                                    <h5>Method Description</h5>
                                    <p>{{ $crypto_analysis['conclusion']['method_descriptions']['description'] }}</p>

                                    @if(isset($crypto_analysis['conclusion']['method_descriptions']['details']))
                                        <ul class="mb-0">
                                            @foreach($crypto_analysis['conclusion']['method_descriptions']['details'] as $detail)
                                                <li>{{ $detail }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                @endif

                                @if(isset($crypto_analysis['conclusion']['general_advice']))
                                <div class="crypto-alert crypto-alert-warning">
                                    <h5>General Trading Advice</h5>
                                    <ul class="mb-0">
                                        @foreach($crypto_analysis['conclusion']['general_advice'] as $advice)
                                            <li>{{ $advice }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    @else
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Analysis Error:</strong> {{ $crypto_analysis['error'] ?? 'Unable to perform analysis' }}
                        </div>
                    @endif
                </x-card>
            </div>
        </div>

        <!-- Analysis Form Section -->
        <div class="row">
            <div class="col-12">
                <x-card title="🎯 Konfigurasi Analisis">
                    <x-form method="GET" action="{{ route(module('getUpdate'), $model) }}">
                        <x-select searchable name="coin_code" :value="$model->coin_code" :options="$coin" label="Select Coin" required/>
                        <x-select name="analyst" :options="$analyst_methods" label="Metode Analisis" :value="request('analyst', 'sniper')" required/>
                        <x-input type="number" name="amount" :value="request('amount', 100)" label="Trading Amount (USD)" step="0.01" min="1" placeholder="Enter amount to trade"/>

                        <x-footer>
                            <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                            <x-button type="submit" class="primary">🔍 Analyze</x-button>
                        </x-footer>
                    </x-form>
                </x-card>
            </div>
        </div>
    </div>
</x-layout>
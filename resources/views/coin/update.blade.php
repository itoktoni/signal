<x-layout>

    <div class="row">
        <!-- Crypto Analysis Section -->
        <div class="col-12">
            <x-card title="🔍 {{ $crypto_analysis['analysis_type'] ?? (isset($crypto_analysis['title']) ? $crypto_analysis['title'] : (AnalysisType::{strtoupper($analyst_method)}()->getAnalysisDescription() ?? 'Basic Analysis')) }} - {{ $model->coin_code ?? 'Tidak Diketahui' }}">
                @if(isset($crypto_analysis) && !isset($crypto_analysis['error']))
                    @php
                        // Standardized analysis result structure from all analysis services
                        $signal = $crypto_analysis['signal'] ?? 'NEUTRAL';
                        $confidence = $crypto_analysis['confidence'] ?? 0;
                        $rrRatio = $crypto_analysis['risk_reward'] ?? 0;
                        $entry = $crypto_analysis['entry'] ?? ['usd' => 0, 'rupiah' => 0];
                        $stopLoss = $crypto_analysis['stop_loss'] ?? ['usd' => 0, 'rupiah' => 0];
                        $takeProfit = $crypto_analysis['take_profit'] ?? ['usd' => 0, 'rupiah' => 0];
                        $title = $crypto_analysis['title'] ?? 'Analysis';

                        $direction = $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');
                        $directionIcon = $signal === 'BUY' ? '🚀' : ($signal === 'SELL' ? '⚠️' : '⏳');
                        $signalClass = $signal === 'BUY' ? 'success' : ($signal === 'SELL' ? 'danger' : 'warning');
                    @endphp

                    <!-- Signal Summary Banner -->
                    <div class="signal-banner {{ $signalClass }}">
                        <div class="signal-content">
                            <h2 class="signal-title">
                                {{ $directionIcon }} {{ $direction }} SIGNAL
                            </h2>
                            <div class="signal-details">
                                <span class="signal-confidence">Confidence: {{ $confidence }}%</span>
                                <span class="signal-rr">Risk-Reward: {{ $rrRatio }}:1</span>
                            </div>
                        </div>
                    </div>
                    <div class="analysis-results">
                        <div class="row">
                            <div class="col-6">
                                <div class="info-card">
                                    <h4>📊 Market Data</h4>
                                    <p><strong>Symbol:</strong> {{ $crypto_analysis['symbol'] ?? $model->coin_code ?? 'Unknown' }}</p>
                                    <p><strong>Current Price:</strong> ${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 8) }}</p>
                                    <p><strong>Trading Amount:</strong> ${{ number_format($amount, 2) }}</p>
                                    <p><strong>Last Updated:</strong> {{ $crypto_analysis['last_updated'] ?? now()->format('Y-m-d H:i:s') }}</p>
                                    <p><strong>Tipe Analisis:</strong>
                                        <span class="badge {{ $analyst_method === AnalysisType::SNIPER ? 'badge-success' : ($analyst_method === AnalysisType::DYNAMIC_RR ? 'badge-primary' : 'badge-warning') }}">
                                            {{ AnalysisType::{strtoupper($analyst_method)}()->getDisplayName() ?? 'Dasar' }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-card">
                                    <h4>🎯 Trading Signal</h4>
                                    @php
                                        $signalClass = $signal === 'BUY' ? 'success' : ($signal === 'SELL' ? 'danger' : 'warning');
                                        $direction = $signal === 'BUY' ? '📈 LONG' : ($signal === 'SELL' ? '📉 SHORT' : '⏸️ NEUTRAL');
                                        $directionIcon = $signal === 'BUY' ? '🚀' : ($signal === 'SELL' ? '⚠️' : '⏳');
                                    @endphp
                                    <p><strong>Direction:</strong>
                                        <span class="badge badge-{{ $signalClass }} signal-large">
                                            {{ $directionIcon }} {{ $direction }}
                                        </span>
                                    </p>
                                    <p><strong>Signal:</strong>
                                        <span class="badge badge-{{ $signalClass }}">
                                            {{ $signal }}
                                        </span>
                                    </p>
                                    <p><strong>Confidence:</strong>
                                        <span class="badge {{ $confidence >= 70 ? 'badge-success' : ($confidence >= 50 ? 'badge-warning' : 'badge-danger') }}">
                                            {{ $confidence }}%
                                        </span>
                                    </p>
                                    <p><strong>Risk-Reward Ratio:</strong>
                                        <span class="badge {{ $rrRatio >= 2 ? 'badge-success' : 'badge-warning' }}">
                                            {{ $rrRatio }}:1
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📈 Trading Levels</h4>
                                    <div class="row">
                                        <div class="col-4">
                                            <p><strong>Entry Point:</strong></p>
                                            <div class="price-dual">
                                                <span class="price-usd">${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}</span>
                                                @if(is_array($entry) && isset($entry['rupiah']))
                                                <span class="price-rupiah">Rp {{ number_format($entry['rupiah'], 0) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <p><strong>Stop Loss:</strong></p>
                                            <div class="price-dual">
                                                <span class="price-usd text-danger">${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }}</span>
                                                @if(is_array($stopLoss) && isset($stopLoss['rupiah']))
                                                <span class="price-rupiah text-danger">Rp {{ number_format($stopLoss['rupiah'], 0) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <p><strong>Take Profit:</strong></p>
                                            <div class="price-dual">
                                                <span class="price-usd text-success">${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }}</span>
                                                @if(is_array($takeProfit) && isset($takeProfit['rupiah']))
                                                <span class="price-rupiah text-success">Rp {{ number_format($takeProfit['rupiah'], 0) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Information Section -->
                        @php
                            $hasFee = false;
                            if (isset($crypto_analysis['fee']) && is_array($crypto_analysis['fee']) &&
                                (isset($crypto_analysis['fee']['usd']) || isset($crypto_analysis['fee']['total']))) {
                                $hasFee = true;
                            }
                        @endphp

                        @if($hasFee)
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>💰 Informasi Biaya</h4>
                                    <div class="row">
                                        <div class="col-6">
                                            <p><strong>Biaya Transaksi:</strong> {{ $fee['breakdown']['description'] ?? 'Biaya transaksi dan pajak' }}</p>
                                            @if(isset($fee['breakdown']))
                                            <div class="fee-breakdown">
                                                <p><strong>Detail Biaya:</strong></p>
                                                <ul class="mb-0">
                                                    <li>Biaya Dasar: {{ $fee['breakdown']['base_fee'] ? '$' . number_format($fee['breakdown']['base_fee'], 2) : 'N/A' }}</li>
                                                    <li>PPN pada Biaya Dasar: {{ $fee['breakdown']['ppn_on_base'] ? '$' . number_format($fee['breakdown']['ppn_on_base'], 2) : 'N/A' }}</li>
                                                    <li>Biaya CFX: {{ $fee['breakdown']['cfx_fee'] ? '$' . number_format($fee['breakdown']['cfx_fee'], 2) : 'N/A' }}</li>
                                                    <li>PPN pada CFX: {{ $fee['breakdown']['ppn_on_cfx'] ? '$' . number_format($fee['breakdown']['ppn_on_cfx'], 2) : 'N/A' }}</li>
                                                    <li>Slippage (Estimasi): {{ $fee['breakdown']['slippage'] ? '$' . number_format($fee['breakdown']['slippage'], 2) : 'N/A' }}</li>
                                                    <li><strong>Total Biaya: {{ $fee['formatted']['both'] }}</strong></li>
                                                </ul>
                                            </div>
                                            @endif
                                        </div>
                                        @if(isset($crypto_analysis['fee']['rupiah']) && $crypto_analysis['fee']['rupiah'] > 0)
                                        <div class="col-6">
                                            <p><strong>Total Fee (Rupiah):</strong></p>
                                            <div class="fee-info">
                                                <span class="price-value">Rp {{ number_format($crypto_analysis['fee']['rupiah'], 0) }}</span>
                                                @if($amount > 0)
                                                <span class="percentage">
                                                    ({{ number_format(($crypto_analysis['fee']['rupiah'] / ($amount * 16000)) * 100, 2) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                        @if(isset($crypto_analysis['fee']['base_fee']) || isset($crypto_analysis['fee']['ppn']) || isset($crypto_analysis['fee']['slippage']) || isset($crypto_analysis['fee']['trading_fee']))
                                        <div class="col-12 mt-3">
                                            <h6>Rincian Biaya:</h6>
                                            <div class="row">
                                                @if(isset($crypto_analysis['fee']['base_fee']))
                                                <div class="col-6"><small>Biaya Transaksi: ${{ number_format($crypto_analysis['fee']['base_fee'], 4) }}</small></div>
                                                @endif
                                                @if(isset($crypto_analysis['fee']['ppn']))
                                                <div class="col-6"><small>PPN: ${{ number_format($crypto_analysis['fee']['ppn'], 4) }}</small></div>
                                                @endif
                                                @if(isset($crypto_analysis['fee']['slippage']))
                                                <div class="col-6"><small>Slippage (0.5%): ${{ number_format($crypto_analysis['fee']['slippage'], 4) }}</small></div>
                                                @endif
                                                @if(isset($crypto_analysis['fee']['trading_fee']))
                                                <div class="col-6"><small>Total Biaya Trading: ${{ number_format($crypto_analysis['fee']['trading_fee'], 4) }}</small></div>
                                                @endif
                                            </div>
                                            @if(isset($crypto_analysis['fee']['description']))
                                            <div class="mt-2">
                                                <small class="text-muted">{{ $crypto_analysis['fee']['description'] }}</small>
                                            </div>
                                            @endif
                                            <div class="mt-2">
                                                <small class="text-info">
                                                    <strong>Contoh:</strong> Kamu melakukan transaksi pembelian BTC senilai ${{ number_format($amount, 0) }}
                                                    sebagai taker. Biaya taker 0,15% ditambah PPN 0,011% juga dikenakan
                                                    dan dihitung dari total jumlah transaksi ( ${{ number_format($amount, 0) }} x 0,161% = ${{ number_format($amount * 0.00161, 2) }} ).
                                                    Dengan begitu, kamu akan menerima BTC senilai ${{ number_format($amount - ($amount * 0.00161), 2) }},
                                                    dengan total pembayaran sebesar ${{ number_format($amount, 2) }} (termasuk semua biaya).
                                                </small>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Profit/Loss Information Section -->
                        @php
                            $hasProfitLoss = false;
                            if (isset($crypto_analysis['potential_profit']) || isset($crypto_analysis['potential_loss'])) {
                                $hasProfitLoss = true;
                            }
                        @endphp

                        @if($hasProfitLoss)
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📊 Profit & Loss Potential</h4>
                                    <div class="row">
                                        @if(isset($crypto_analysis['potential_profit']))
                                        <div class="col-6">
                                            <p><strong>Potential Profit:</strong></p>
                                            <div class="profit-loss-info">
                                                @php
                                                    $profitValue = is_array($crypto_analysis['potential_profit']) ? $crypto_analysis['potential_profit']['usd'] : $crypto_analysis['potential_profit'];
                                                    $profitClass = $profitValue >= 0 ? 'text-success' : 'text-danger';
                                                @endphp
                                                <span class="price-value {{ $profitClass }}">${{ number_format($profitValue, 2) }}</span>
                                                @if(is_array($crypto_analysis['potential_profit']) && isset($crypto_analysis['potential_profit']['rupiah']))
                                                <span class="price-rupiah {{ $profitClass }}">Rp {{ number_format($crypto_analysis['potential_profit']['rupiah'], 0) }}</span>
                                                @endif
                                                @if($amount > 0)
                                                <span class="percentage {{ $profitClass }}">
                                                    ({{ number_format(($profitValue / $amount) * 100, 1) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                        @if(isset($crypto_analysis['potential_loss']))
                                        <div class="col-6">
                                            <p><strong>Potential Loss:</strong></p>
                                            <div class="profit-loss-info">
                                                @php
                                                    $lossValue = is_array($crypto_analysis['potential_loss']) ? $crypto_analysis['potential_loss']['usd'] : $crypto_analysis['potential_loss'];
                                                    // Loss should typically be negative, but we'll handle both cases
                                                    $displayLoss = abs($lossValue);
                                                @endphp
                                                <span class="price-value text-danger">${{ number_format($displayLoss, 2) }}</span>
                                                @if(is_array($crypto_analysis['potential_loss']) && isset($crypto_analysis['potential_loss']['rupiah']))
                                                <span class="price-rupiah text-danger">Rp {{ number_format(abs($crypto_analysis['potential_loss']['rupiah']), 0) }}</span>
                                                @endif
                                                @if($amount > 0)
                                                <span class="percentage text-danger">
                                                    ({{ number_format(($displayLoss / $amount) * 100, 1) }}%)
                                                </span>
                                                @endif
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @php
                            // Check if indicators exist in the analysis data
                            $hasIndicators = false;
                            if (isset($crypto_analysis['indicators']) && is_array($crypto_analysis['indicators'])) {
                                $indicators = $crypto_analysis['indicators'];
                                $hasIndicators = !empty($indicators);
                            }

                            // Get indicator configuration from the analysis result
                            $currentConfig = $crypto_analysis['indicator_config'] ?? [];
                        @endphp

                        @if($hasIndicators)
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card">
                                    <h4>📊 Technical Indicators</h4>
                                    <div class="row">
                                        @foreach($currentConfig as $key => $config)
                                            @if(isset($indicators[$key]))
                                            <div class="{{ $config['class'] }}">
                                                <p><strong>{{ $config['label'] }}:</strong></p>
                                                @if(isset($config['text_class']))
                                                <span class="{{ $config['text_class'] }}">
                                                @else
                                                <span>
                                                @endif
                                                    @if($config['format'] === 'price')
                                                        ${{ number_format($indicators[$key], 3) }}
                                                    @else
                                                        {{ $indicators[$key] }}
                                                    @endif
                                                </span>
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Kesimpulan Section -->
                        <div class="row">
                            <div class="col-12">
                                <div class="info-card kesimpulan-section">
                                    <h4>📋 Kesimpulan</h4>
                                    @if($signal === 'BUY')
                                        <div class="kesimpulan-content">
                                            <p class="kesimpulan-text">
                                                <strong>💚 REKOMENDASI: ENTRY LONG</strong><br>
                                                Berdasarkan analisis teknikal menggunakan strategi
                                                {{ AnalysisType::{strtoupper($analyst_method)}()->getDisplayName() ?? 'Dasar' }},
                                                kondisi pasar saat ini menunjukkan peluang yang baik untuk posisi <strong>BUY/LONG</strong>.
                                                Semua indikator berada dalam formasi bullish yang kuat dengan
                                                level confidence {{ $confidence }}%.
                                            </p>

                                            <div class="kesimpulan-details">
                                                <div class="detail-item">
                                                    <span class="detail-label">🎯 Entry Point:</span>
                                                    <span class="detail-value">${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🛡️ Stop Loss:</span>
                                                    <span class="detail-value text-danger">${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🎯 Take Profit:</span>
                                                    <span class="detail-value text-success">${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">📊 Risk-Reward:</span>
                                                    <span class="detail-value">{{ $rrRatio }}:1</span>
                                                </div>
                                            </div>

                                            <div class="kesimpulan-recommendation">
                                                <br>
                                                <strong>💡 Rekomendasi Trading:</strong><br>
                                                • Entry pada level ${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}<br>
                                                • Set stop loss di ${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }} untuk membatasi risiko<br>
                                                • Target profit di ${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }} dengan potensi {{ $rrRatio }}x lipat dari risiko<br>
                                                • Confidence level {{ $confidence }}% - {{ $confidence >= 80 ? 'SANGAT DIREKOMENDASIKAN' : ($confidence >= 60 ? 'DIREKOMENDASIKAN' : 'PERTIMBANGKAN HATI-HATI') }}
                                            </div>
                                        </div>

                                    @elseif($signal === 'SELL')
                                        <div class="kesimpulan-content">
                                            <p class="kesimpulan-text">
                                                <strong>❤️ REKOMENDASI: ENTRY SHORT</strong><br>
                                                Analisis teknikal dengan strategi
                                                {{ AnalysisType::{strtoupper($analyst_method)}()->getDisplayName() ?? 'Dasar' }}
                                                menunjukkan kondisi pasar yang menguntungkan untuk posisi <strong>SELL/SHORT</strong>.
                                                Formasi indikator menunjukkan tren bearish yang kuat dengan confidence level {{ $confidence }}%.
                                            </p>

                                            <div class="kesimpulan-details">
                                                <div class="detail-item">
                                                    <span class="detail-label">🎯 Entry Point:</span>
                                                    <span class="detail-value">${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🛡️ Stop Loss:</span>
                                                    <span class="detail-value text-danger">${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">🎯 Take Profit:</span>
                                                    <span class="detail-value text-success">${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }}</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">📊 Risk-Reward:</span>
                                                    <span class="detail-value">{{ $rrRatio }}:1</span>
                                                </div>
                                            </div>

                                            <div class="kesimpulan-recommendation">
                                                <strong>💡 Rekomendasi Trading:</strong><br>
                                                • Entry short pada level ${{ number_format(is_array($entry) ? ($entry['usd'] ?? 0) : $entry, 3) }}<br>
                                                • Set stop loss di ${{ number_format(is_array($stopLoss) ? ($stopLoss['usd'] ?? 0) : $stopLoss, 3) }} untuk membatasi risiko<br>
                                                • Target profit di ${{ number_format(is_array($takeProfit) ? ($takeProfit['usd'] ?? 0) : $takeProfit, 3) }} dengan potensi {{ $rrRatio }}x lipat dari risiko<br>
                                                • Confidence level {{ $confidence }}% - {{ $confidence >= 80 ? 'SANGAT DIREKOMENDASIKAN' : ($confidence >= 60 ? 'DIREKOMENDASIKAN' : 'PERTIMBANGKAN HATI-HATI') }}
                                            </div>
                                        </div>

                                    @else
                                        <div class="kesimpulan-content">
                                            <p class="kesimpulan-text">
                                                <strong>💛 KONDISI: NETRAL</strong><br>
                                                Berdasarkan analisis
                                                {{ AnalysisType::{strtoupper($analyst_method)}()->getDisplayName() ?? 'Dasar' }},
                                                kondisi pasar saat ini menunjukkan sinyal <strong>NETRAL</strong>.
                                                Tidak ada formasi yang cukup kuat untuk merekomendasikan posisi BUY atau SELL dengan confidence yang tinggi ({{ $confidence }}%).
                                            </p>

                                            <div class="kesimpulan-recommendation">
                                                <strong>💡 Rekomendasi:</strong><br>
                                                • Tunggu konfirmasi sinyal yang lebih jelas<br>
                                                • Monitor pergerakan harga untuk setup yang lebih baik<br>
                                                • Pertimbangkan untuk tidak entry sampai ada sinyal yang lebih konfirm<br>
                                                • Confidence level {{ $confidence }}% - menunjukkan ketidakpastian pasar
                                            </div>
                                        </div>
                                    @endif
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

                <!-- Analysis Conclusion -->
                @if(isset($crypto_analysis) && !isset($crypto_analysis['error']) && isset($crypto_analysis['conclusion']))
                <div class="analysis-results info-card mt-4">
                    <h4>🔍 Kesimpulan Analisis</h4>

                    <!-- Signal Recommendation -->
                    <div class="alert {{ $crypto_analysis['conclusion']['signal'] === 'BUY' ? 'alert-success' : ($crypto_analysis['conclusion']['signal'] === 'SELL' ? 'alert-danger' : 'alert-warning') }}">
                        <h5>{{ $crypto_analysis['conclusion']['recommendations']['title'] }}</h5>
                        <p><strong>Kekuatan Sinyal:</strong> Tingkat kepercayaan {{ $crypto_analysis['conclusion']['confidence'] }}%</p>
                        <p><strong>Rasio Risiko-Hadiah:</strong> {{ $crypto_analysis['conclusion']['rr_ratio'] }}:1</p>
                        <p>{{ $crypto_analysis['conclusion']['recommendations']['description'] }}</p>

                        @if(isset($crypto_analysis['conclusion']['method_descriptions']['description']))
                            <p><em>{{ $crypto_analysis['conclusion']['method_descriptions']['description'] }}</em></p>
                        @endif
                    </div>

                    <!-- Trading Advice -->
                    @if(isset($crypto_analysis['conclusion']['recommendations']['trading_advice']))
                    <div class="alert alert-info">
                        <h6>💡 Rekomendasi Trading:</h6>
                        <ul>
                            @foreach($crypto_analysis['conclusion']['recommendations']['trading_advice'] as $advice)
                                <li>{{ $advice }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <!-- Method Description -->
                    @if(isset($crypto_analysis['conclusion']['method_descriptions']['details']))
                    <div class="mt-3 p-3 bg-light border rounded">
                        <h5>📚 Deskripsi Metode:</h5>
                        <ul>
                            @foreach($crypto_analysis['conclusion']['method_descriptions']['details'] as $detail)
                                <li>{{ $detail }}</li>
                            @endforeach
                        </ul>

                        @if(isset($crypto_analysis['conclusion']['method_descriptions']['advantages']))
                        <div class="alert alert-info mt-2">
                            <h6>💡 Keuntungan Utama:</h6>
                            <ul>
                                @foreach($crypto_analysis['conclusion']['method_descriptions']['advantages'] as $advantage)
                                    <li>{{ $advantage }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </div>
                    @endif

                    <!-- General Trading Advice -->
                    @if(isset($crypto_analysis['conclusion']['general_advice']))
                    <div class="mt-3 p-3 bg-warning text-dark border rounded">
                        <h5>⚠️ Catatan Penting Trading:</h5>
                        <ul>
                            @foreach($crypto_analysis['conclusion']['general_advice'] as $advice)
                                <li>{{ $advice }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <!-- Market Context -->
                    <div class="alert alert-info mt-3">
                        <h5>🌐 Konteks Pasar</h5>
                        <p><strong>Harga Saat Ini:</strong> ${{ number_format((is_array($entry) ? ($entry['usd'] ?? 0) : $entry) ?? 0, 8) }}</p>
                        <p><strong>Terakhir Diperbarui:</strong> {{ $crypto_analysis['last_updated'] ?? now()->format('Y-m-d H:i:s') }}</p>
                        <p>Analisis ini berdasarkan indikator teknis dan harus dikombinasikan dengan analisis fundamental dan berita pasar untuk keputusan perdagangan yang optimal.</p>
                    </div>
                </div>
                @endif
            </x-card>
        </div>

        <!-- Analysis Form Section -->
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

    <style>
    .analysis-results {
        margin: 0px 2rem;

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
    .price-dual {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .price-usd {
        font-size: 14px;
        font-weight: bold;
    }
    .price-rupiah {
        font-size: 12px;
        opacity: 0.8;
    }
    .profit-loss-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .percentage {
        font-size: 12px;
        font-weight: bold;
        margin-top: 2px;
    }
    .fee-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
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
    .badge-primary {
        background-color: #007bff;
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
    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }
    .alert-info {
        color: #0c5460;
        background-color: #d1ecf1;
        border-color: #bee5eb;
    }

    /* Enhanced Signal Display */
    .signal-large {
        font-size: 16px !important;
        padding: 8px 16px !important;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .badge-success.signal-large {
        background-color: #28a745 !important;
        color: white !important;
        animation: pulse-green 2s infinite;
    }

    .badge-danger.signal-large {
        background-color: #dc3545 !important;
        color: white !important;
        animation: pulse-red 2s infinite;
    }

    .badge-warning.signal-large {
        background-color: #ffc107 !important;
        color: #212529 !important;
        animation: pulse-yellow 2s infinite;
    }

    @keyframes pulse-green {
        0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
    }

    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
    }

    @keyframes pulse-yellow {
        0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
    }

    /* Signal Banner Styles */
    .signal-banner {
        border-radius: 12px;
        padding: 20px;
        margin: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .signal-banner::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
        animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }

    .signal-banner.success {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }

    .signal-banner.danger {
        background: linear-gradient(135deg, #dc3545, #fd7e14);
        color: white;
    }

    .signal-banner.warning {
        background: linear-gradient(135deg, #ffc107, #fd7e14);
        color: #212529;
    }

    /* Analysis Description Styles */
    .info-card ul {
        padding-left: 20px;
    }

    .info-card li {
        margin-bottom: 8px;
    }

    .alert h5 {
        margin-top: 0;
    }

    .alert ul {
        padding-left: 20px;
        margin-bottom: 0;
    }
    </style>
</x-layout>
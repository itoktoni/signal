<x-layout>
    <x-card title="Edit Trade">
        <x-form :model="$model" method="POST" action="{{ route(module('postUpdate'), $model->trade_id) }}">

            <div class="row">
                <x-select name="symbol" label="Trading Pair" :options="$trading_pairs" :value="$model->symbol" required col="6" />
                <x-select name="side" label="Side" :options="['buy' => 'Buy', 'sell' => 'Sell']" :value="$model->side" required col="3" />
                <x-select name="type" label="Order Type" :options="[
                    'market' => 'Market',
                    'limit' => 'Limit',
                    'stop' => 'Stop',
                    'stop_limit' => 'Stop Limit'
                ]" :value="$model->type" required col="3" />
            </div>

            <div class="row">
                <x-input name="amount" type="number" step="0.00000001" label="Amount" :value="$model->amount" required col="6" />
                <x-input name="price" type="number" step="0.01" label="Price (for limit orders)" :value="$model->price" col="6" />
            </div>

            <div class="row">
                <x-input name="trading_plan_id" type="text" label="Trading Plan ID" :value="$model->trading_plan_id" col="6" />
                <x-input name="notes" type="textarea" label="Notes" :value="$model->notes" col="6" />
            </div>

            @if($model->analysis_result)
            <div class="row">
                <div class="col-12">
                    <label class="form-label">Analysis Result</label>
                    <pre><code>{{ json_encode($model->analysis_result, JSON_PRETTY_PRINT) }}</code></pre>
                </div>
            </div>
            @endif

            <x-footer>
                <a href="{{ route(module('getShow'), $model->trade_id) }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">Update Trade</x-button>
            </x-footer>

        </x-form>
    </x-card>

    @if($model->status === 'pending')
    <x-card title="Trade Actions">
        <div class="row">
            <div class="col-12">
                <p>Execute this trade on the exchange or cancel it.</p>
                <button type="button" class="button success" onclick="executeTrade()">
                    <i class="bi bi-play"></i> Execute Trade
                </button>
                <button type="button" class="button danger" onclick="cancelTrade()">
                    <i class="bi bi-stop"></i> Cancel Trade
                </button>
            </div>
        </div>
    </x-card>
    @endif

    @if(in_array($model->status, ['open', 'filled']))
    <x-card title="Exchange Information">
        <div class="row">
            <div class="col-md-6">
                @if($model->exchange_order_id)
                <div class="form-group">
                    <strong>Exchange Order ID:</strong> {{ $model->exchange_order_id }}
                </div>
                @endif
                @if($model->executed_at)
                <div class="form-group">
                    <strong>Executed At:</strong> {{ $model->executed_at->format('Y-m-d H:i:s') }}
                </div>
                @endif
            </div>
            <div class="col-md-6">
                @if($model->cost)
                <div class="form-group">
                    <strong>Total Cost:</strong> ${{ number_format($model->cost, 4) }}
                </div>
                @endif
                @if($model->fee)
                <div class="form-group">
                    <strong>Fee:</strong> {{ number_format($model->fee, 8) }} {{ $model->fee_currency }}
                </div>
                @endif
            </div>
        </div>

        @if($model->exchange_response)
        <div class="row">
            <div class="col-12">
                <div class="form-group">
                    <strong>Exchange Response:</strong>
                    <pre><code>{{ json_encode($model->exchange_response, JSON_PRETTY_PRINT) }}</code></pre>
                </div>
            </div>
        </div>
        @endif
    </x-card>
    @endif

    <script>
        function executeTrade() {
            if (confirm('Are you sure you want to execute this trade?')) {
                fetch(`{{ route('trade.postExecute', $model->trade_id) }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Trade executed successfully!');
                        location.reload();
                    } else {
                        alert('Failed to execute trade: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error executing trade: ' + error.message);
                });
            }
        }

        function cancelTrade() {
            if (confirm('Are you sure you want to cancel this trade?')) {
                fetch(`{{ route('trade.postCancel', $model->trade_id) }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Trade cancelled successfully!');
                        location.reload();
                    } else {
                        alert('Failed to cancel trade: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Error cancelling trade: ' + error.message);
                });
            }
        }
    </script>
</x-layout>
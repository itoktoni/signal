<x-layout>
    @section('header')
        <div class="header-title-wrapper is-vertical-align">
            <button id="mobile-menu-button" class="mobile-menu-button safe-area-left">
                <i class="bi bi-sliders"></i>
            </button>
            <h1 class="header-title">TRADE MANAGEMENT</h1>
        </div>
        <div class="user-profile is-vertical-align safe-area-right">
            <div class="notification-icon" id="notification-icon">
                <i class="bi bi-bell"></i><span class="notification-badge">2</span>
            </div>
            <div class="profile-icon" id="profile-icon">
                <i class="bi bi-person-badge"></i>
                <div class="profile-dropdown" id="profile-dropdown">
                    <a href="{{ route('profile') }}" class="dropdown-item">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="dropdown-item"
                            style="background: none; border: none; padding: 0; cursor: pointer; text-align: left; width: 100%;">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    @endsection

    <div class="card">
        <div class="page-header">
            <h2>Trade Details</h2>
            <div>
                <span class="badge {{ $model->status_color }} fs-6">{{ $model->status_text }}</span>
            </div>
        </div>
        <div class="card-table">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <strong>Trade ID:</strong> {{ $model->trade_id }}
                    </div>
                    <div class="form-group">
                        <strong>Symbol:</strong> {{ $model->symbol }}
                    </div>
                    <div class="form-group">
                        <strong>Side:</strong>
                        <span class="badge {{ $model->side === 'buy' ? 'success' : 'danger' }}">
                            {{ ucfirst($model->side) }}
                        </span>
                    </div>
                    <div class="form-group">
                        <strong>Type:</strong> {{ ucfirst($model->type) }}
                    </div>
                    <div class="form-group">
                        <strong>Amount:</strong> {{ number_format($model->amount, 8) }}
                    </div>
                    @if($model->price)
                    <div class="form-group">
                        <strong>Price:</strong> ${{ number_format($model->price, 4) }}
                    </div>
                    @endif
                </div>
                <div class="col-md-6">
                    @if($model->entry_price)
                    <div class="form-group">
                        <strong>Entry Price:</strong> ${{ number_format($model->entry_price, 4) }}
                    </div>
                    @endif
                    @if($model->stop_loss)
                    <div class="form-group">
                        <strong>Stop Loss:</strong> ${{ number_format($model->stop_loss, 4) }}
                    </div>
                    @endif
                    @if($model->take_profit)
                    <div class="form-group">
                        <strong>Take Profit:</strong> ${{ number_format($model->take_profit, 4) }}
                    </div>
                    @endif
                    @if($model->risk_reward_ratio)
                    <div class="form-group">
                        <strong>Risk:Reward Ratio:</strong> {{ $model->risk_reward_ratio }}:1
                    </div>
                    @endif
                    @if($model->confidence)
                    <div class="form-group">
                        <strong>Confidence:</strong> {{ $model->confidence }}%
                    </div>
                    @endif
                </div>
            </div>

            @if($model->pnl !== null)
            <div class="row mt-3">
                <div class="col-12">
                    <div class="alert {{ $model->pnl >= 0 ? 'alert-success' : 'alert-danger' }}">
                        <h5>Trade Result</h5>
                        <p class="mb-0">
                            <strong>P&L:</strong> {{ $model->formatted_pnl }}<br>
                            <strong>P&L Percentage:</strong> {{ number_format($model->pnl_percentage, 2) }}%
                        </p>
                    </div>
                </div>
            </div>
            @endif

            @if($model->exchange_order_id)
            <div class="row mt-3">
                <div class="col-12">
                    <div class="form-group">
                        <strong>Exchange Order ID:</strong> {{ $model->exchange_order_id }}
                    </div>
                </div>
            </div>
            @endif

            @if($model->trading_plan_id)
            <div class="row mt-3">
                <div class="col-12">
                    <div class="form-group">
                        <strong>Trading Plan ID:</strong> {{ $model->trading_plan_id }}
                    </div>
                </div>
            </div>
            @endif

            @if($model->analysis_method)
            <div class="row mt-3">
                <div class="col-12">
                    <div class="form-group">
                        <strong>Analysis Method:</strong> {{ ucfirst(str_replace('_', ' ', $model->analysis_method)) }}
                    </div>
                </div>
            </div>
            @endif

            @if($model->notes)
            <div class="row mt-3">
                <div class="col-12">
                    <div class="form-group">
                        <strong>Notes:</strong>
                        <p class="mt-2">{{ $model->notes }}</p>
                    </div>
                </div>
            </div>
            @endif

            @if($model->exchange_response)
            <div class="row mt-3">
                <div class="col-12">
                    <div class="form-group">
                        <strong>Exchange Response:</strong>
                        <pre class="mt-2"><code>{{ json_encode($model->exchange_response, JSON_PRETTY_PRINT) }}</code></pre>
                    </div>
                </div>
            </div>
            @endif

            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="form-group">
                        <strong>Created At:</strong> {{ $model->created_at->format('Y-m-d H:i:s') }}
                    </div>
                </div>
                @if($model->executed_at)
                <div class="col-md-6">
                    <div class="form-group">
                        <strong>Executed At:</strong> {{ $model->executed_at->format('Y-m-d H:i:s') }}
                    </div>
                </div>
                @endif
            </div>

            @if($model->closed_at)
            <div class="row mt-3">
                <div class="col-12">
                    <div class="form-group">
                        <strong>Closed At:</strong> {{ $model->closed_at->format('Y-m-d H:i:s') }}
                    </div>
                </div>
            </div>
            @endif

            <x-footer>
                @if(in_array($model->status, ['pending', 'open']))
                    <x-button class="success" :attributes="['onclick' => 'executeTrade()']">Execute Trade</x-button>
                    <x-button class="warning" :attributes="['onclick' => 'cancelTrade()']">Cancel Trade</x-button>
                @endif

                <a href="{{ route(module('getUpdate'), $model->trade_id) }}" class="button primary">Edit Trade</a>

                @if(in_array($model->status, ['pending', 'cancelled', 'rejected']))
                    <form method="POST" action="{{ route(module('getDelete'), $model->trade_id) }}" class="inline"
                        onsubmit="return confirm('Are you sure you want to delete this trade?')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" class="danger">Delete Trade</x-button>
                    </form>
                @endif
            </x-footer>
        </div>
    </div>

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
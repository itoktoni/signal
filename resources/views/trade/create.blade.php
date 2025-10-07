<x-layout>
    <x-card title="Create New Trade">
        <x-form action="{{ route(module('postCreate')) }}">

            <div class="row">
                <x-select name="symbol" label="Trading Pair" :options="$trading_pairs" required col="6" />
                <x-select name="side" label="Side" :options="['buy' => 'Buy', 'sell' => 'Sell']" required col="3" />
                <x-select name="type" label="Order Type" :options="[
                    'market' => 'Market',
                    'limit' => 'Limit',
                    'stop' => 'Stop',
                    'stop_limit' => 'Stop Limit'
                ]" required col="3" />
            </div>

            <div class="row">
                <x-input name="amount" type="number" step="0.00000001" label="Amount" required col="6" />
                <x-input name="price" type="number" step="0.01" label="Price (for limit orders)" col="6" />
            </div>

            <div class="row">
                <x-input name="trading_plan_id" type="text" label="Trading Plan ID" col="6" />
                <x-input name="notes" type="textarea" label="Notes" col="6" />
            </div>

            <div class="row">
                <div class="col-12">
                    <x-toggle name="auto_execute" label="Auto Execute on Exchange" />
                </div>
            </div>

            <x-footer>
                <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">Create Trade</x-button>
            </x-footer>

        </x-form>
    </x-card>
</x-layout>
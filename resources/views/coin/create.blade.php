<x-layout>
        <x-card title="Create Coin">
            <x-form action="{{ route(module('postCreate')) }}">

                <x-input name="coin_code" hint="Coin cannot be changed" required/>
                <x-input name="coin_name" required/>
                <x-input name="coin_symbol" required/>

                <x-footer>
                    <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                    <x-button type="submit" class="primary">Create</x-button>
                </x-footer>

            </x-form>
        </x-card>
</x-layout>
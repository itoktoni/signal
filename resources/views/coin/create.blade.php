<x-layout>
        <x-card title="Create Form">
            <x-form action="{{ route(module('postCreate')) }}">

                <x-input name="coin_code" hint="Coin cannot be changed" required/>
                <x-input name="coin_base" required/>
                <x-input name="coin_asset" required/>

                <x-footer>
                    <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                    <x-button type="submit" class="primary">Create</x-button>
                </x-footer>

            </x-form>
        </x-card>
</x-layout>
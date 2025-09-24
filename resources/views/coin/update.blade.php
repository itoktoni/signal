<x-layout>

    <x-card title="Edit User">
        <x-form action="{{ route(module('postUpdate'), $model) }}">

            <x-input :value="$model->coin_code" name="coin_code" hint="Coin cannot be changed" required/>
            <x-input :value="$model->coin_base" name="coin_base" required/>
            <x-input :value="$model->coin_asset" name="coin_asset" required/>


            <x-footer>
                <a href="{{ route(module('index')) }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">Update</x-button>
            </x-footer>

        </x-form>
    </x-card>
</x-layout>

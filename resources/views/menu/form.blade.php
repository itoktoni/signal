<x-layout>
    <x-card :title="$model ? 'Edit Menu' : 'Create Menu'">
        <x-form
            :model="$model ?? null"
            :action="$model ? route(module('postUpdate'), $model) : route(module('postCreate'))"
            :method="$model ? 'POST' : 'POST'"
        >
            @if($model)
                @method('PUT')
            @endif

            <x-input
                :model="$model ?? null"
                name="menu_code"
                :hint="$model ? 'Menu code cannot be changed' : null"
                :required="!$model"
                :readonly="$model ? true : null"
            />

            <x-input
                :model="$model ?? null"
                name="menu_name"
                :value="$model->menu_name ?? null"
                required
            />

            <x-input
                :model="$model ?? null"
                name="menu_group"
                :value="$model->menu_group ?? null"
                required
            />

            <x-input
                :model="$model ?? null"
                name="menu_controller"
                :value="$model->menu_controller ?? null"
            />

            <x-input
                :model="$model ?? null"
                name="menu_action"
                :value="$model->menu_action ?? null"
            />

            <x-footer>
                <a href="{{ route(module('index')) }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">
                    {{ $model ? 'Update' : 'Create' }}
                </x-button>
            </x-footer>

        </x-form>
    </x-card>
</x-layout>
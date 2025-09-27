<x-layout>
    <x-card :title="$model ? 'Edit Group' : 'Create Group'">
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
                name="group_code"
                :hint="$model ? 'Group code cannot be changed' : null"
                :required="!$model"
                :readonly="$model ? true : null"
            />

            <x-input
                :model="$model ?? null"
                name="group_name"
                :value="$model->group_name ?? null"
                required
            />

            <x-input
                :model="$model ?? null"
                name="group_icon"
                :value="$model->group_icon ?? null"
            />

            <x-input
                :model="$model ?? null"
                name="group_link"
                :value="$model->group_link ?? null"
            />

            <x-input
                :model="$model ?? null"
                type="number"
                name="group_sort"
                :value="$model->group_sort ?? null"
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
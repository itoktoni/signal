<x-layout>
    <x-card :model="$model">
        <x-form :model="$model">
            <x-input
                :model="$model"
                name="group_code"
                :attributes="isset($model) ? ['readonly' => true] : []"
                hint="Group code cannot be changed"
            />

            <x-input
                :model="$model"
                name="group_name"
                required
            />

            <x-input
                :model="$model"
                name="group_icon"
            />

            <x-input
                :model="$model"
                name="group_link"
            />

            <x-input
                :model="$model"
                type="number"
                name="group_sort"
            />

            <x-footer :model="$model" />

        </x-form>
    </x-card>
</x-layout>
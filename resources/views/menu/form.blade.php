<x-layout>
    <x-card :model="$model">
        <x-form :model="$model">

            <x-input
                :model="$model"
                name="menu_code"
                :attributes="isset($model) ? ['readonly' => true] : []"
                hint="Menu code cannot be changed"
            />

            <x-input
                :model="$model"
                name="menu_name"
                required
            />

            <x-input
                :model="$model"
                name="menu_group"
                required
            />

            <x-input
                :model="$model"
                name="menu_controller"
            />

            <x-input
                :model="$model"
                name="menu_action"
            />

            <x-footer :model="$model" />

        </x-form>
    </x-card>
</x-layout>
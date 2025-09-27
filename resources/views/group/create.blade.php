<x-layout>
    <x-card title="Create Form">
        <x-form action="{{ route(module('postCreate')) }}">
            <x-input name="menu_code" required/>

            <x-input name="menu_name" required/>

            <x-input name="menu_controller" col="12" required/>

            <x-select name="menu_group" :options="['' => 'Select Role', 'admin' => 'Admin', 'user' => 'User', 'manager' => 'Manager']" required/>

            <x-footer>
                <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">Create</x-button>
            </x-footer>

        </x-form>
    </x-card>
</x-layout>
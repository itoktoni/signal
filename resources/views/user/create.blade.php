<x-layout>
    <x-card title="Create Form">
        <x-form action="{{ route(module('postCreate')) }}">
            <x-input name="username" hint="Username cannot be changed" required/>

            <x-input name="name" required/>

            <x-input type="email" name="email" col="12" required/>

            <x-input type="password" name="password" required minlength="6"/>

            <x-input type="password" name="password_confirmation" required/>

            <x-select name="role" :options="['' => 'Select Role', 'admin' => 'Admin', 'user' => 'User', 'manager' => 'Manager']" required/>

            <x-footer>
                <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">Create</x-button>
            </x-footer>

        </x-form>
    </x-card>
</x-layout>
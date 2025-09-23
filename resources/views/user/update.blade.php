<x-layout>

    <x-card title="Edit User">
        <x-form action="{{ route(module('postUpdate'), $model) }}">
            <x-input name="username" :value="$model->username" hint="Username cannot be changed" readonly/>

            <x-input name="name" :value="$model->name" required/>

            <x-input type="email" name="email" :value="$model->email" required/>

            <x-input type="password" name="password" minlength="6"/>

            <x-input type="password" name="password_confirmation"/>

            <x-select name="role" :value="$model->role" :options="['' => 'Select Role', 'admin' => 'Admin', 'user' => 'User', 'manager' => 'Manager']"/>

            <x-footer>
                <a href="{{ route(module('index')) }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">Update</x-button>
            </x-footer>

        </x-form>
    </x-card>
</x-layout>

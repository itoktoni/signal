<x-layout>

    <div class="card">
        <div class="page-header">
            <h2>Edit User</h2>
        </div>

        <x-form action="{{ route('user.postUpdate', $model) }}">
            <x-input name="username" :value="old('username', $model->username)" hint="Username cannot be changed" readonly/>

            <x-input name="name" :value="old('name', $model->name)" required/>

            <x-input type="email" name="email" :value="old('email', $model->email)" required/>

            <x-input type="password" name="password" minlength="6"/>

            <x-input type="password" name="password_confirmation"/>

            <x-select name="role" :options="['' => 'Select Role', 'admin' => 'Admin', 'user' => 'User', 'manager' => 'Manager']" :value="old('role', $model->role)"/>

            <x-footer>
                <a href="{{ route('user.index') }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">Update</x-button>
            </x-footer>
        </x-form>
    </div>
</x-layout>

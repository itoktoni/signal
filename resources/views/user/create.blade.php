<x-layout>

        <div class="card">
            <div class="page-header">
                <h2>Create Form</h2>
            </div>

            <form class="form-container" action="{{ route('user.postCreate') }}" method="POST">
                @csrf
                <x-input name="username" hint="Username cannot be changed" required/>

                <x-input name="name" required/>

                <x-input type="email" name="email" col="12" required/>

                <x-input type="password" name="password" required minlength="6"/>

                <x-input type="password" name="password_confirmation" required/>

                <x-select name="st" searchable :options="['' => 'Select Role', 'admin' => 'Admin', 'user' => 'User', 'manager' => 'Manager']" required/>
                <x-select name="role" searchable :options="['' => 'Select Role', 'admin' => 'Admin', 'user' => 'User', 'manager' => 'Manager']" required/>

                <x-footer>
                    <a href="{{ route('user.index') }}" class="button secondary">Back</a>
                    <x-button type="submit" class="primary">Create</x-button>
                </x-footer>
            </form>
    </div>
</x-layout>
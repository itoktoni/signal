<x-layout>

    <div class="card">
        <div class="page-header">
            <h2>Edit User</h2>
        </div>

        <form class="form-container" action="{{ route('user.postUpdate', $model) }}" method="POST">
            @csrf

            <x-input name="name" :value="old('name', $model->name)" required/>

            <x-input type="email" name="email" :value="old('email', $model->email)" required/>

            <x-footer>
                <x-button type="submit" class="primary">Update User</x-button>
                <a href="{{ route('user.index') }}" class="button secondary">Cancel</a>
            </x-footer>
        </form>
    </div>
    </div>
</x-layout>

<x-layout>

    <div class="card">
        <div class="page-header">
            <h2>Edit User</h2>
        </div>

        <form class="form-container" action="{{ route('user.postUpdate', $model) }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="name" class="form-label">Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $model->name) }}"
                    class="form-input" required>
                @error('name')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email', $model->email) }}"
                    class="form-input" required>
                @error('email')
                    <span class="field-error">{{ $message }}</span>
                @enderror
            </div>

            <footer class="content-footer safe-area-bottom">
            <div class="form-actions">
                <button type="submit" class="button primary">
                    Update User
                </button>
                <a href="{{ route('user.index') }}" class="button secondary">Cancel</a>
            </div>
            </footer>
        </form>
    </div>
    </div>
</x-layout>

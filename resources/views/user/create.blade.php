<x-layout>

        <div class="card">
            <div class="page-header">
                <h2>Create Form</h2>
            </div>

            <form class="form-container" action="{{ route('user.postCreate') }}" method="POST">
                @csrf
                <div class="form-group col-6">
                    <label for="username" class="form-label">
                        Username<span class="required-asterisk">*</span>
                    </label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="form-input"
                        value="{{ old('username') }}"
                        required
                    />
                    <div class="field-hint">Username cannot be changed</div>
                    @error('username') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group col-6">
                    <label for="name" class="form-label">
                        Name<span class="required-asterisk">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-input"
                        value="{{ old('name') }}"
                        required
                    />
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group col-12">
                    <label for="email" class="form-label">
                        Email<span class="required-asterisk">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-input"
                        value="{{ old('email') }}"
                        required
                    />
                    @error('email') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group col-6">
                    <label for="password" class="form-label">
                        Password<span class="required-asterisk">*</span>
                    </label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        required
                        minlength="6"
                    />
                    @error('password') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group col-6">
                    <label for="password_confirmation" class="form-label">
                        Password Confirmation<span class="required-asterisk">*</span>
                    </label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        class="form-input"
                        required
                    />
                    @error('password_confirmation') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group col-6">
                    <label for="role" class="form-label">
                        Role<span class="required-asterisk">*</span>
                    </label>
                    <select
                        id="role"
                        name="role"
                        class="form-select"
                        required
                    >
                        <option value="">Select Role</option>
                        <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>User</option>
                        <option value="manager" {{ old('role') == 'manager' ? 'selected' : '' }}>Manager</option>
                    </select>
                    @error('role') <span class="field-error">{{ $message }}</span> @enderror
                </div>

                <footer class="content-footer safe-area-bottom">
                <div class="form-actions">
                    <a href="{{ route('user.index') }}" class="button secondary">
                        Back
                    </a>
                    <button type="submit" class="button primary">
                        Create
                    </button>
                </div>
                </footer>
            </form>
    </div>
</x-layout>
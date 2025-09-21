<x-template-layout>
    @section('header')
        <div class="header-title-wrapper is-vertical-align">
            <button id="mobile-menu-button" class="mobile-menu-button safe-area-left">
                <i class="bi bi-sliders"></i>
            </button>
            <h1 class="header-title">ASSET MANAGEMENT</h1>
        </div>
        <div class="user-profile is-vertical-align safe-area-right">
            <div class="notification-icon" id="notification-icon">
                <i class="bi bi-bell"></i><span class="notification-badge">2</span>
            </div>
            <div class="profile-icon" id="profile-icon">
                <i class="bi bi-person-badge"></i>
                <div class="profile-dropdown" id="profile-dropdown">
                    <a href="{{ route('profile.show') }}" class="dropdown-item">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="dropdown-item" style="background: none; border: none; padding: 0; cursor: pointer; text-align: left; width: 100%;">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    @endsection

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
</x-template-layout>
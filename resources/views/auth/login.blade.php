<x-auth-template-layout>
    <x-validation-errors class="mb-4" />

    @session('status')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ $value }}
        </div>
    @endsession

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input id="email" class="form-input" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
        </div>

        <div class="form-group">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input id="password" class="form-input" type="password" name="password" required autocomplete="current-password" />
        </div>

        <div class="form-group">
            <label for="remember_me" class="form-checkbox">
                <input id="remember_me" type="checkbox" name="remember" />
                <span>{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="auth-actions">
            <div class="auth-links">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="auth-link">{{ __('Forgot your password?') }}</a>
                @endif
            </div>
            <button type="submit" class="btn btn-primary">{{ __('Log in') }}</button>
        </div>
    </form>
</x-auth-template-layout>

<form class="form-container" wire:submit="updatePassword">
    @csrf

    <div class="form-group">
        <label for="current_password" class="form-label">{{ __('Current Password') }}</label>
        <input id="current_password" type="password" class="form-input" wire:model="state.current_password" autocomplete="current-password" />
        @error('current_password')
            <span class="field-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="form-group">
        <label for="password" class="form-label">{{ __('New Password') }}</label>
        <input id="password" type="password" class="form-input" wire:model="state.password" autocomplete="new-password" />
        @error('password')
            <span class="field-error">{{ $message }}</span>
        @enderror
    </div>

    <div class="form-group">
        <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
        <input id="password_confirmation" type="password" class="form-input" wire:model="state.password_confirmation" autocomplete="new-password" />
        @error('password_confirmation')
            <span class="field-error">{{ $message }}</span>
        @enderror
    </div>

    <footer class="content-footer safe-area-bottom">
        <div class="form-actions">
            <x-action-message class="me-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <button type="submit" class="button primary">
                {{ __('Save') }}
            </button>
        </div>
    </footer>
</form>

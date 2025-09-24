<form class="form-container" wire:submit="updateProfileInformation">
    @csrf

    <!-- Profile Photo -->
    @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
        <div x-data="{ photoName: null, photoPreview: null }" class="form-group">
            <!-- Profile Photo File Input -->
            <input type="file" id="photo" class="hidden" wire:model.live="photo" x-ref="photo"
                x-on:change="
                                photoName = $refs.photo.files[0].name;
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    photoPreview = e.target.result;
                                };
                                reader.readAsDataURL($refs.photo.files[0]);
                        " />

            <label for="photo" class="form-label">{{ __('Photo') }}</label>

            <!-- Current Profile Photo -->
            <div class="mt-2" x-show="! photoPreview">
                <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}"
                    class="rounded-full size-20 object-cover">
            </div>

            <!-- New Profile Photo Preview -->
            <div class="mt-2" x-show="photoPreview" style="display: none;">
                <span class="block rounded-full size-20 bg-cover bg-no-repeat bg-center"
                    x-bind:style="'background-image: url(\'' + photoPreview + '\');'">
                </span>
            </div>

            <button type="button" class="button secondary mt-2 me-2" x-on:click.prevent="$refs.photo.click()">
                {{ __('Select A New Photo') }}
            </button>

            @if ($this->user->profile_photo_path)
                <button type="button" class="button secondary mt-2" wire:click="deleteProfilePhoto">
                    {{ __('Remove Photo') }}
                </button>
            @endif

            @error('photo')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>
    @endif

    <!-- Name -->
    <div class="form-group col-6">
        <label for="name" class="form-label">{{ __('Name') }}</label>
        <input id="name" type="text" class="form-input" wire:model="state.name" required autocomplete="name" />
        @error('name')
            <span class="field-error">{{ $message }}</span>
        @enderror
    </div>

    <!-- Email -->
    <div class="form-group">
        <label for="email" class="form-label">{{ __('Email') }}</label>
        <input id="email" type="email" class="form-input" wire:model="state.email" required autocomplete="username" />
        @error('email')
            <span class="field-error">{{ $message }}</span>
        @enderror

        @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) &&
                !$this->user->hasVerifiedEmail())
            <p class="text-sm mt-2">
                {{ __('Your email address is unverified.') }}

                <button type="button"
                    class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    wire:click.prevent="sendEmailVerification">
                    {{ __('Click here to re-send the verification email.') }}
                </button>
            </p>

            @if ($this->verificationLinkSent)
                <p class="mt-2 font-medium text-sm text-green-600">
                    {{ __('A new verification link has been sent to your email address.') }}
                </p>
            @endif
        @endif
    </div>

    <footer class="content-footer safe-area-bottom">
        <div class="form-actions">
            <x-action-message class="me-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <button type="submit" class="button primary" wire:loading.attr="disabled" wire:target="photo">
                {{ __('Save') }}
            </button>
        </div>
    </footer>
</form>

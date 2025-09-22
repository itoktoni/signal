<div class="form-group col-{{ $col }}">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if($required)<span class="required-asterisk">*</span>@endif
    </label>
    @if($searchable)
        <div class="custom-select-wrapper" data-multiple="{{ $multiple ? 'true' : 'false' }}">
            <button type="button" class="custom-select-input">
                <div class="custom-select-placeholder">{{ $placeholder ?: (isset($options['']) ? $options[''] : 'Select an option') }}</div>
                <div class="custom-select-arrow">▼</div>
            </button>
            <input type="hidden" name="{{ $name }}{{ $multiple ? '[]' : '' }}" id="{{ $id }}" @if($required) required @endif />
            <div class="custom-select-dropdown">
                <input type="text" class="custom-select-search" placeholder="Search...">
                <div class="custom-select-options">
                    @foreach($options as $key => $option)
                        <div class="custom-select-option" data-value="{{ $key }}" {{ $value == $key ? 'data-selected="true"' : '' }}>
                            {{ $option }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <select
            name="{{ $name }}{{ $multiple ? '[]' : '' }}"
            id="{{ $id }}"
            @if($required) required @endif
            @if($multiple) multiple @endif
            class="{{ $class }}"
            @foreach($attributes as $key => $value)
                {{ $key }}="{{ $value }}"
            @endforeach
        >
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach($options as $key => $option)
                <option value="{{ $key }}" {{ $value == $key ? 'selected' : '' }}>{{ $option }}</option>
            @endforeach
        </select>
    @endif
    @if($hint)
        <div class="field-hint">{{ $hint }}</div>
    @endif
    @error($name) <span class="field-error">{{ $message }}</span> @enderror
</div>
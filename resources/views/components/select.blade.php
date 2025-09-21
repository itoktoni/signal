@props([
    'name',
    'options' => [],
    'value' => '',
    'col' => 6
])

@php
    $label = ucfirst(str_replace('_', ' ', $name));
    $value = old($name, $value);
    $error = $errors->first($name);
    $required = in_array($name, ['name', 'email', 'password', 'password_confirmation', 'role']);
@endphp

<div class="form-group col-{{ $col }}">
    <label for="{{ $name }}" class="form-label">
        {{ $label }}
        @if($required) <span class="required-asterisk">*</span> @endif
    </label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        class="form-select{{ $error ? ' error' : '' }}"
        @if($required) required @endif
        {{ $attributes->except(['col']) }}
    >
        @foreach($options as $key => $option)
            <option value="{{ $key }}" {{ $value == $key ? 'selected' : '' }}>{{ $option }}</option>
        @endforeach
    </select>
    @if($error)
        <span class="field-error">{{ $error }}</span>
    @endif
</div>
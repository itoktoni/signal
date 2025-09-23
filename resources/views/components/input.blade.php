@props(['col' => 12, 'type' => 'text', 'id' => '', 'name' => '', 'value' => '', 'placeholder' => '', 'required' => false, 'class' => 'form-input', 'attributes' => [], 'label' => '', 'hint' => ''])

<div class="form-group col-{{ $col }}">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if($required)<span class="required-asterisk">*</span>@endif
    </label>
    <input
        type="{{ $type }}"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ is_string($value) ? $value : '' }}"
        placeholder="{{ is_string($placeholder) ? $placeholder : '' }}"
        @if($required) required @endif
        class="{{ $class }}"
        @foreach($attributes as $key => $val)
            {{ $key }}="{{ $val }}"
        @endforeach
    />
    @if($hint)
        <div class="field-hint">{{ $hint }}</div>
    @endif
    @error($name) <span class="field-error">{{ $message }}</span> @enderror
</div>

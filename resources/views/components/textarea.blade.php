@props(['col' => 12, 'name' => '', 'value' => '', 'placeholder' => '', 'required' => false, 'class' => 'form-textarea', 'id' => '', 'rows' => 3, 'attributes' => [], 'label' => '', 'hint' => ''])

@php
    $textareaValue = $value;
    if ($name) {
        $textareaValue = old($name, $value);
    }
@endphp

<div class="form-group col-{{ $col }}">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if($required)<span class="required-asterisk">*</span>@endif
    </label>
    <textarea
        name="{{ $name }}"
        id="{{ $id }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        class="{{ $class }}"
        rows="{{ $rows }}"
        @foreach($attributes as $key => $val)
            {{ $key }}="{{ $val }}"
        @endforeach
    >{{ $textareaValue }}</textarea>
    @if($hint)
        <div class="field-hint">{{ $hint }}</div>
    @endif
    @error($name) <span class="field-error">{{ $message }}</span> @enderror
</div>
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
        @foreach($attributes as $key => $value)
            {{ $key }}="{{ $value }}"
        @endforeach
    >{{ $value }}</textarea>
    @if($hint)
        <div class="field-hint">{{ $hint }}</div>
    @endif
    @error($name) <span class="field-error">{{ $message }}</span> @enderror
</div>
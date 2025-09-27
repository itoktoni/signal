@props(['col' => 12, 'type' => 'text', 'id' => '', 'name' => '', 'value' => '', 'model' => null, 'placeholder' => '', 'required' => false, 'class' => 'form-input', 'attributes' => [], 'label' => '', 'hint' => ''])

<div class="form-group col-{{ $col }}">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if($required)<span class="required-asterisk">*</span>@endif
    </label>
    @php
        $inputValue = $value;
        $currentModel = null;

        // Try to get model from props
        if ($model) {
            $currentModel = $model;
        } else {
            // Try to get from route parameters (for update forms)
            $route = request()->route();
            if ($route) {
                $parameters = $route->parameters();
                foreach ($parameters as $param) {
                    if (is_object($param) && method_exists($param, 'getAttributes')) {
                        $currentModel = $param;
                        break;
                    }
                }
            }
        }

        if ($currentModel && $name) {
            $inputValue = old($name, $model->$name);
        } elseif ($name) {
            $inputValue = old($name, $value);
        }
    @endphp
    <input
        type="{{ $type }}"
        id="{{ $id }}"
        name="{{ $name }}"
        value="{{ is_string($inputValue) ? $inputValue : '' }}"
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


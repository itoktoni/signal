@props(['col' => 12, 'name' => '', 'value' => '', 'model' => null, 'options' => [], 'placeholder' => '', 'required' => false, 'multiple' => false, 'searchable' => false, 'class' => 'form-select', 'id' => '', 'attributes' => [], 'label' => '', 'hint' => ''])

@php
    $selectValue = $value;
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

    if ($currentModel && $name && property_exists($currentModel, $name)) {
        $selectValue = old($name, $currentModel->$name);
    } elseif ($name) {
        $selectValue = old($name, $value);
    }

    // Ensure selectValue is properly typed for comparison
    if (is_string($selectValue)) {
        $selectValue = trim($selectValue);
    }
@endphp

<div class="form-group col-{{ $col }}">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if($required)<span class="required-asterisk">*</span>@endif
    </label>
    <select
        name="{{ $name }}@if($multiple)[]@endif"
        id="{{ $id }}"
        @if($required) required @endif
        class="{{ $class }} @if($searchable) tom-select @endif"
        @if($searchable) data-searchable="true" @endif
        @if($multiple) multiple @endif
        @if($placeholder) data-placeholder="{{ $placeholder }}" @endif
        @foreach($attributes as $key => $value)
            {{ $key }}="{{ $value }}"
        @endforeach
    >
        @if($placeholder && !$multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $key => $option)
            <option value="{{ $key }}" {{ ($multiple && is_array($selectValue) && in_array($key, $selectValue)) || (!$multiple && (string)$selectValue === (string)$key) ? 'selected' : '' }}>{{ $option }}</option>
        @endforeach
    </select>
    @if($hint)
        <div class="field-hint">{{ $hint }}</div>
    @endif
    @error($name) <span class="field-error">{{ $message }}</span> @enderror
</div>
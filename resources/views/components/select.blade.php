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
@endphp

<div class="form-group col-{{ $col }}">
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if($required)<span class="required-asterisk">*</span>@endif
    </label>
    @if($multiple || $searchable)
        <div class="custom-select-wrapper {{ $multiple ? 'custom-select-multiple' : '' }}" data-multiple="{{ $multiple ? 'true' : 'false' }}">
            <button type="button" class="custom-select-input">
                <div class="custom-select-placeholder">{{ $multiple ? ($placeholder ?: 'Select options') : ($placeholder ?: (isset($options['']) ? $options[''] : 'Select an option')) }}</div>
                <div class="custom-select-arrow">▼</div>
            </button>
            @if(!$multiple)<input type="hidden" name="{{ $name }}" id="{{ $id }}" @if($required) required @endif />@endif
            @if($multiple)<div class="custom-select-hidden-inputs" data-name="{{ $name }}[]"></div>@endif
            @if($multiple)
            <div class="custom-select-selected-items">
                <!-- Selected items will be populated by JS -->
            </div>
            @endif
            <div class="custom-select-dropdown">
                @if($searchable)<input type="text" class="custom-select-search" placeholder="Search...">@endif
                <div class="custom-select-options">
                    @foreach($options as $key => $option)
                        <div class="custom-select-option" data-value="{{ $key }}" {{ ($multiple && is_array($selectValue) && in_array($key, $selectValue)) || (!$multiple && $selectValue == $key) ? 'data-selected="true"' : '' }}>
                            {{ $option }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <select
            name="{{ $name }}"
            id="{{ $id }}"
            @if($required) required @endif
            class="{{ $class }}"
            @foreach($attributes as $key => $value)
                {{ $key }}="{{ $value }}"
            @endforeach
        >
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach($options as $key => $option)
                <option value="{{ $key }}" {{ $selectValue == $key ? 'selected' : '' }}>{{ $option }}</option>
            @endforeach
        </select>
    @endif
    @if($hint)
        <div class="field-hint">{{ $hint }}</div>
    @endif
    @error($name) <span class="field-error">{{ $message }}</span> @enderror
</div>
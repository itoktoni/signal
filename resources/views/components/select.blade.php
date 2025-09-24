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
        @php
            $initialPlaceholder = $placeholder ?: (isset($options['']) ? $options[''] : 'Select an option');
            $selectedText = '';

            // Get the text of the selected option
            if (!$multiple && $selectValue && isset($options[$selectValue])) {
                $selectedText = $options[$selectValue];
                $initialPlaceholder = $selectedText;
            }
        @endphp
        <div class="modern-select-wrapper {{ $multiple ? 'modern-select-multiple' : '' }}" data-multiple="{{ $multiple ? 'true' : 'false' }}" data-placeholder="{{ $placeholder ?: (isset($options['']) ? $options[''] : 'Select an option') }}">
            <div class="modern-select-container">
                <div class="modern-select-display">
                    <span class="modern-select-text {{ $selectedText ? 'has-value' : 'empty' }}" data-placeholder="{{ $placeholder ?: (isset($options['']) ? $options[''] : 'Select an option') }}">{{ $initialPlaceholder }}</span>
                    <div class="modern-select-icons">
                        @if($searchable)
                            <svg class="modern-select-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                        @endif
                        <svg class="modern-select-arrow {{ $selectedText ? 'rotated' : '' }}" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6,9 12,15 18,9"></polyline>
                        </svg>
                    </div>
                </div>
                @if(!$multiple)<input type="hidden" name="{{ $name }}" id="{{ $id }}" @if($required) required @endif value="{{ $selectValue }}"/>@endif
                @if($multiple)<div class="modern-select-hidden-inputs" data-name="{{ $name }}[]"></div>@endif
                @if($multiple)
                <div class="modern-select-tags">
                    <!-- Selected items will be populated by JS -->
                </div>
                @endif
                <div class="modern-select-dropdown">
                    @if($searchable)
                        <div class="modern-select-search-container">
                            <svg class="modern-select-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <path d="m21 21-4.35-4.35"></path>
                            </svg>
                            <input type="text" class="modern-select-search" placeholder="Search options...">
                        </div>
                    @endif
                    <div class="modern-select-options">
                        @foreach($options as $key => $option)
                            <div class="modern-select-option {{ ($multiple && is_array($selectValue) && in_array($key, $selectValue)) || (!$multiple && $selectValue == $key) ? 'selected' : '' }}" data-value="{{ $key }}">
                                <span class="modern-select-option-text">{{ $option }}</span>
                                @if(($multiple && is_array($selectValue) && in_array($key, $selectValue)) || (!$multiple && $selectValue == $key))
                                    <svg class="modern-select-check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20,6 9,17 4,12"></polyline>
                                    </svg>
                                @endif
                            </div>
                        @endforeach
                    </div>
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
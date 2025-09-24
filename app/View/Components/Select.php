<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Select extends Component
{
    public $name;

    public $options;

    public $value;

    public $placeholder;

    public $required;

    public $class;

    public $id;

    public $label;

    public $hint;

    public $col;

    public $searchable;

    public $multiple;

    public $optionKey;

    public $optionValue;

    public $attributes;

    public function __construct(
        $name = null,
        $options = [],
        $value = null,
        $placeholder = null,
        $required = false,
        $class = 'form-select',
        $id = null,
        $label = null,
        $hint = null,
        $col = 6,
        $searchable = false,
        $multiple = false,
        $optionKey = 'id',
        $optionValue = 'name',
        $attributes = []
    ) {
        $this->name = $name;
        $this->options = $this->normalizeOptions($options, $optionKey, $optionValue);
        $this->value = $value ?? old($name);
        $this->placeholder = $placeholder;
        $this->required = $required;
        $this->class = $class;
        $this->id = $id ?? $name;
        $this->label = $label ?? ucfirst(str_replace('_', ' ', $name));
        $this->hint = $hint;
        $this->col = $col;
        $this->searchable = $searchable;
        $this->multiple = $multiple;
        $this->optionKey = $optionKey;
        $this->optionValue = $optionValue;
    }

    private function normalizeOptions($options, $key, $value)
    {
        if ($options instanceof \Illuminate\Support\Collection) {
            return $options->pluck($value, $key)->toArray();
        }

        if (is_array($options) && ! empty($options)) {
            $first = reset($options);
            if (is_object($first) && isset($first->$key) && isset($first->$value)) {
                $result = [];
                foreach ($options as $option) {
                    $result[$option->$key] = $option->$value;
                }

                return $result;
            }
        }

        return $options;
    }

    public function render()
    {
        return view('components.select');
    }
}

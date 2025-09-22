<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Input extends Component
{
    public $type;
    public $name;
    public $value;
    public $placeholder;
    public $required;
    public $class;
    public $id;
    public $label;
    public $hint;
    public $col;
    public $attributes;

    public function __construct(
        $type = 'text',
        $name = null,
        $value = null,
        $placeholder = null,
        $required = false,
        $class = 'form-input',
        $id = null,
        $label = null,
        $hint = null,
        $col = 6,
        $attributes = []
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->value = $value ?? old($name);
        $this->placeholder = $placeholder;
        $this->required = $required;
        $this->class = $class;
        $this->id = $id ?? $name;
        $this->label = $label ?? ucfirst(str_replace('_', ' ', $name));
        $this->hint = $hint;
        $this->col = $col;
    }

    public function render()
    {
        return view('components.input');
    }
}
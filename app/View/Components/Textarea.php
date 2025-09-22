<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Textarea extends Component
{
    public $name;
    public $value;
    public $placeholder;
    public $required;
    public $class;
    public $id;
    public $rows;
    public $label;
    public $hint;
    public $col;
    public $attributes;

    public function __construct(
        $name = null,
        $value = null,
        $placeholder = null,
        $required = false,
        $class = 'form-input',
        $id = null,
        $rows = 3,
        $label = null,
        $hint = null,
        $col = 6,
        $attributes = []
    ) {
        $this->name = $name;
        $this->value = $value ?? old($name);
        $this->placeholder = $placeholder;
        $this->required = $required;
        $this->class = $class;
        $this->id = $id ?? $name;
        $this->rows = $rows;
        $this->label = $label ?? ucfirst(str_replace('_', ' ', $name));
        $this->hint = $hint;
        $this->col = $col;
    }

    public function render()
    {
        return view('components.textarea');
    }
}
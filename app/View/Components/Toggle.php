<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Toggle extends Component
{
    public $name;
    public $value;
    public $checked;
    public $class;
    public $id;
    public $attributes;

    public function __construct(
        $name = null,
        $value = null,
        $checked = false,
        $class = 'form-toggle',
        $id = null,
        $attributes = []
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->checked = $checked;
        $this->class = $class;
        $this->id = $id;
        $this->attributes = $attributes;
    }

    public function render()
    {
        return view('components.toggle');
    }
}
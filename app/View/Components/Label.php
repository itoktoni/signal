<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Label extends Component
{
    public $for;

    public $class;

    public $id;

    public $attributes;

    public $slot;

    public function __construct(
        $for = null,
        $class = 'form-label',
        $id = null,
        $attributes = []
    ) {
        $this->for = $for;
        $this->class = $class;
        $this->id = $id;
        $this->attributes = $attributes;
    }

    public function render()
    {
        return view('components.label');
    }
}

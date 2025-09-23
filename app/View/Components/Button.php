<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Button extends Component
{
    public $type;

    public $class;

    public $id;

    public $attributes;

    public function __construct(
        $type = 'button',
        $class = 'button',
        $id = null,
        $attributes = []
    ) {
        $this->type = $type;
        $this->class = $class;
        $this->id = $id;
    }

    public function render()
    {
        return view('components.button');
    }
}

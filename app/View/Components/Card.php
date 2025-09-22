<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Card extends Component
{
    public $class;
    public $id;
    public $attributes;
    public $slot;

    public function __construct(
        $class = 'card',
        $id = null,
        $attributes = []
    ) {
        $this->class = $class;
        $this->id = $id;
        $this->attributes = $attributes;
    }

    public function render()
    {
        return view('components.card');
    }
}
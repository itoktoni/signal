@props(['action', 'method' => 'POST', 'class' => 'form-container', 'model'])

@php
    $action = $action ?? '';
    if (empty($action) && function_exists('module')) {
        if ($model) {
            $action = route(module('postUpdate'), $model);
        } else {
            $action = route(module('postCreate'));
        }
    }
@endphp

<form class="{{ $class }}" action="{{ $action }}" method="{{ $method }}">
    @csrf
    {{ $slot }}
</form>
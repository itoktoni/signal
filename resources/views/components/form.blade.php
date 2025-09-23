@props(['action', 'method' => 'POST', 'class' => 'form-container'])

<form class="{{ $class }}" action="{{ $action }}" method="{{ $method }}">
    @csrf
    {{ $slot }}
</form>
@props(['id' => null, 'type' => 'button', 'class' => '', 'attributes' => []])

<button
    type="{{ $type }}"
    class="{{ $class }}"
    @if($id) id="{{ $id }}" @endif
    @foreach($attributes as $key => $value)
        {{ $key }}="{{ $value }}"
    @endforeach
>
    {{ $slot }}
</button>

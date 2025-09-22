<div
    class="{{ $class }}"
    @if($id) id="{{ $id }}" @endif
    @foreach($attributes as $key => $value)
        {{ $key }}="{{ $value }}"
    @endforeach
>
    {{ $slot }}
</div>
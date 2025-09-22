<label
    @if($for) for="{{ $for }}" @endif
    class="{{ $class }}"
    @if($id) id="{{ $id }}" @endif
    @foreach($attributes as $key => $value)
        {{ $key }}="{{ $value }}"
    @endforeach
>
    {{ $slot }}
</label>

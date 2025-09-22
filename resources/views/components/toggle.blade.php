<input
    type="checkbox"
    name="{{ $name }}"
    value="{{ $value }}"
    @if($checked) checked @endif
    class="{{ $class }}"
    @if($id) id="{{ $id }}" @endif
    @foreach($attributes as $key => $value)
        {{ $key }}="{{ $value }}"
    @endforeach
/>
@props(['column', 'route', 'text'])

<a href="{{ sortUrl($column, $route) }}" class="{{ request('sort') === $column ? 'sorted' : '' }}">
    {{ $text }}
    @if(request('sort') === $column)
        <i class="ml-1 bi bi-sort-{{ request('direction') === 'asc' ? 'up-alt' : 'down' }}"></i>
    @endif
</a>
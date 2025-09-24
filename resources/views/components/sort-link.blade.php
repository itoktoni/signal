@props(['column', 'route', 'text'])

<a href="{{ sortUrl($column, $route) }}" class="{{ request('sort') === $column ? 'sorted' : '' }}">
    {{ $text }}
    @if(request('sort') === $column)
        <i class="bi bi-chevron-{{ request('direction') === 'asc' ? 'up' : 'down' }}"></i>
    @endif
</a>
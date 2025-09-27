@props(['column', 'text', 'sortable' => false])

<th>
    @if($sortable)
        <x-sort-link column="{{ $column }}" route="{{ module('getData') }}" text="{{ $text }}" />
    @else
        {{ $text }}
    @endif
</th>
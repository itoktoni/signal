@props(['title', 'model'])

@php
    $title = $title ?? '';
    if (empty($title) && $model) {
        $title = 'Edit ' . class_basename($model);
    } elseif (empty($title)) {
        $title = 'Create Form';
    }
@endphp

<div
    class="{{ $class ?? 'card' }}"
    @if($id) id="{{ $id }}" @endif
    @foreach($attributes as $key => $value)
        {{ $key }}="{{ $value }}"
    @endforeach
>
    @if($title)
        <div class="page-header">
            <h2>{{ $title }}</h2>
        </div>
    @endif
    {{ $slot }}
</div>
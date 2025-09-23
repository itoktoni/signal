@props(['title', 'class' => 'card'])

<div class="{{ $class }}">
    <div class="page-header">
        <h2>{{ $title }}</h2>
    </div>
    {{ $slot }}
</div>
@props(['model'])

<div class="action-table">
    <a href="{{ route(module('getUpdate'), $model) }}" class="button primary">
        <i class="bi bi-pencil-square"></i>
    </a>

    <button type="button" class="button danger" onclick="confirmDelete('{{ route(module('getDelete'), $model) }}', '{{ $model->name }}')">
        <i class="bi bi-trash"></i>
    </button>

    {{ $slot }}
</div>
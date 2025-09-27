@props(['model', 'type' => 'form'])

<footer class="content-footer safe-area-bottom">
    <div class="form-actions">
        @if($slot->isEmpty())
            @if($type === 'form')
                <a href="{{ route(module('getData')) }}" class="button secondary">Back</a>
                <x-button type="submit" class="primary">{{ isset($model) ? 'Update' : 'Create' }}</x-button>
            @elseif($type === 'list')
                <button type="button" class="button danger" id="bulk-delete-btn" disabled onclick="confirmBulkDelete()">Delete</button>
                <a href="{{ route(module('getCreate')) }}" class="button success">
                    <i class="bi bi-plus"></i>Create
                </a>
            @endif
        @else
            {{ $slot }}
        @endif
    </div>
</footer>
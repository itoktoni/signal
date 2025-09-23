@props(['data'])

<div class="form-table-pagination">
    <nav class="pagination">
        @if ($data->onFirstPage())
            <button disabled="" class="button secondary">
                <i class="bi bi-arrow-left"></i>
            </button>
        @else
            <a href="{{ $data->previousPageUrl() }}" class="button secondary">
                <i class="bi bi-arrow-left"></i>
            </a>
        @endif
        <span class="pagination-info"> Page {{ $data->currentPage() }} of
            {{ $data->lastPage() }}
        </span>
        @if ($data->hasMorePages())
            <a href="{{ $data->nextPageUrl() }}" class="button secondary">
                <i class="bi bi-arrow-right"></i>
            </a>
        @else
            <button disabled="" class="button secondary">
                <i class="bi bi-arrow-right"></i>
            </button>
        @endif
    </nav>
</div>
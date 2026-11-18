<x-layouts.app :title="$buyer->name" :back="route('buyers.index')">
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                <div class="d-flex gap-3 align-items-center min-w-0">
                    <span class="stat-icon tone-purple"><i class="bi bi-person-vcard"></i></span>
                    <div class="min-w-0">
                        <h1 class="h4 fw-bold mb-0">{{ $buyer->name }}</h1>
                        <div class="text-muted"><i class="bi bi-geo-alt"></i> {{ $buyer->city }}</div>
                    </div>
                </div>
                <div class="page-actions">
                    <a href="{{ route('buyers.edit', $buyer) }}" class="btn btn-light"><i class="bi bi-pencil"></i> Edit</a>
                    @can('delete', $buyer)
                        <form method="POST" action="{{ route('buyers.destroy', $buyer) }}" data-confirm="This buyer has no dispatches yet, so it can be removed permanently." data-confirm-title="Delete {{ $buyer->name }}?" data-confirm-button="Delete">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    @endcan
                </div>
            </div>
            <hr>
            <dl class="detail-list">
                <div><dt>Contact person</dt><dd>{{ $buyer->contact_person ?: '—' }}</dd></div>
                <div><dt>Phone</dt><dd>@if ($buyer->phone)<a href="tel:{{ $buyer->phone }}"><i class="bi bi-telephone"></i> {{ $buyer->phone }}</a>@else — @endif</dd></div>
                <div><dt>Address</dt><dd>{{ $buyer->address ?: '—' }}</dd></div>
                <div><dt>Added</dt><dd>{{ $buyer->created_at->format('d M Y') }}</dd></div>
                @if ($buyer->notes)<div style="grid-column: 1 / -1"><dt>Notes</dt><dd class="fw-normal">{!! nl2br(e($buyer->notes)) !!}</dd></div>@endif
            </dl>
        </div>
    </div>
</x-layouts.app>

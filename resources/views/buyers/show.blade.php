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
                    @include('partials.pdf-choice', ['title' => $buyer->name, 'options' => [
                        [route('buyers.pdf', ['buyer' => $buyer, 'status' => $status, 'part' => 'samples']), 'Samples sent', 'Every sample sent, the answer, agreed quantity and rate, and how much is loaded.', 'basket'],
                        [route('buyers.pdf', ['buyer' => $buyer, 'status' => $status, 'part' => 'loadings']), 'Truck loadings', 'Every truck loaded for this buyer, with its photos.', 'truck'],
                    ]])
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

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><x-stat-card label="Awaiting response" :value="$counts['pending'] ?? 0" icon="hourglass-split" tone="gold" :href="route('buyers.show', [$buyer, 'status' => 'pending'])" /></div>
        <div class="col-6 col-md-3"><x-stat-card label="Confirmed" :value="$counts['confirmed'] ?? 0" icon="check-circle" tone="green" :href="route('buyers.show', [$buyer, 'status' => 'confirmed'])" /></div>
        <div class="col-6 col-md-3"><x-stat-card label="Rejected" :value="$counts['rejected'] ?? 0" icon="x-circle" tone="red" :href="route('buyers.show', [$buyer, 'status' => 'rejected'])" /></div>
        <div class="col-6 col-md-3"><x-stat-card label="Completed orders" :value="$counts['completed'] ?? 0" icon="check2-all" tone="teal" :href="route('buyers.show', [$buyer, 'status' => 'completed'])" /></div>
    </div>

    <div class="card">
        <div class="card-header justify-content-between">
            <h2>Dispatch &amp; order history</h2>
            <a href="{{ route('dispatches.create', ['buyer_ids' => [$buyer->id]]) }}" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-send"></i> Send samples</a>
        </div>
        <nav class="nav-tabs-pills" aria-label="Filter by status">
            @foreach ([null => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'rejected' => 'Rejected', 'completed' => 'Completed'] as $key => $label)
                <a href="{{ route('buyers.show', [$buyer, 'status' => $key ?: null]) }}" @class(['active' => $status === ($key ?: null)])>{{ $label }}<span class="count">{{ $counts[$key ?: 'all'] ?? 0 }}</span></a>
            @endforeach
        </nav>
        @if ($dispatches->isEmpty())
            <x-empty-state icon="send" :title="$status ? 'Nothing in this list' : 'No samples sent to this buyer yet'" />
        @else
            @include('dispatches._table', ['dispatches' => $dispatches, 'hideBuyer' => true])
            @if ($dispatches->hasPages())<div class="card-footer">{{ $dispatches->links() }}</div>@endif
        @endif
    </div>
</x-layouts.app>

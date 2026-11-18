<div class="table-responsive">
    <table class="table table-hover table-stack align-middle">
        <thead><tr><th>Mill</th><th>Contact</th><th>Phone</th><th class="text-end"></th></tr></thead>
        <tbody>
        @foreach ($mills as $mill)
            <tr class="row-link" data-href="{{ route('mills.show', $mill) }}">
                <td class="cell-primary">
                    <a href="{{ route('mills.show', $mill) }}" class="cell-title">{{ $mill->name }}</a>
                    <div class="cell-sub"><i class="bi bi-geo-alt"></i> {{ $mill->city }}</div>
                </td>
                <td data-label="Contact"><span>{{ $mill->contact_person ?: '—' }}</span></td>
                <td data-label="Phone"><span>@if ($mill->phone)<a href="tel:{{ $mill->phone }}">{{ $mill->phone }}</a>@else — @endif</span></td>
                <td class="text-end hide-mobile"><i class="bi bi-chevron-right text-muted"></i></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

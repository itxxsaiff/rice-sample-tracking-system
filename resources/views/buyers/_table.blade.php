<div class="table-responsive">
    <table class="table table-hover table-stack align-middle">
        <thead><tr><th>Buyer</th><th>Contact</th><th>Phone</th><th class="text-end"></th></tr></thead>
        <tbody>
        @foreach ($buyers as $buyer)
            <tr class="row-link" data-href="{{ route('buyers.show', $buyer) }}">
                <td class="cell-primary">
                    <a href="{{ route('buyers.show', $buyer) }}" class="cell-title">{{ $buyer->name }}</a>
                    <div class="cell-sub"><i class="bi bi-geo-alt"></i> {{ $buyer->city }}</div>
                </td>
                <td data-label="Contact"><span>{{ $buyer->contact_person ?: '—' }}</span></td>
                <td data-label="Phone"><span>@if ($buyer->phone)<a href="tel:{{ $buyer->phone }}">{{ $buyer->phone }}</a>@else — @endif</span></td>
                <td class="text-end hide-mobile"><i class="bi bi-chevron-right text-muted"></i></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

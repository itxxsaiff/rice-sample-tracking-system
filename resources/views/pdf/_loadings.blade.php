{{-- Truck loading register: same columns as the on-screen loadings table. --}}
@php $hideBuyer ??= false; @endphp
<table class="grid">
    <thead>
        <tr>
            <th class="no">#</th>
            <th>Truck</th>
            @unless ($hideBuyer)<th>Buyer</th>@endunless
            <th>Sample</th>
            <th>Date</th>
            <th>Driver</th>
            <th class="r">Quantity</th>
            <th class="r">Photos</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($loadings as $loading)
            <tr>
                <td class="no">{{ $loop->iteration }}</td>
                <td class="nowrap">
                    <div class="strong">{{ $loading->truck_number }}</div>
                    <div class="sub">{{ $loading->loading_number }} · {{ $loading->dispatch->dispatch_number }}</div>
                </td>
                @unless ($hideBuyer)<td class="strong">{{ $loading->dispatch->buyer->name }}</td>@endunless
                <td>
                    <div class="strong">{{ $loading->dispatch->sample->sample_number }} · {{ $loading->dispatch->sample->variety->name }}</div>
                    <div class="sub">{{ $loading->dispatch->sample->mill->name }}</div>
                </td>
                <td class="nowrap">{{ $loading->loading_date->format('d M Y') }}</td>
                <td>{{ $loading->driver_name ?: '—' }}@if ($loading->driver_phone)<div class="sub">{{ $loading->driver_phone }}</div>@endif</td>
                <td class="r nowrap">
                    <div class="strong">{{ $loading->quantityLabel() }}</div>
                    @if ($loading->bag_count)<div class="sub">{{ number_format($loading->bag_count) }} bags{{ $loading->packing_type ? ' of '.$loading->packing_type : '' }}</div>@endif
                </td>
                <td class="r">{{ $loading->photos_count ?? $loading->photos->count() }}</td>
                <td><span class="st st-{{ $loading->isCompleted() ? 'completed' : 'in_progress' }}">{{ $loading->status->label() }}</span></td>
            </tr>
        @endforeach
    </tbody>
    @if ($loadings->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="{{ $hideBuyer ? 5 : 6 }}">Total loaded</td>
                <td class="r nowrap">
                    {{ $loadings->groupBy('quantity_unit')->map(fn ($rows, $unit) => \App\Models\SampleDispatch::formatQuantity($rows->sum('quantity'), $unit))->implode(', ') }}
                    @if ($bags = $loadings->sum('bag_count'))<div class="sub">{{ number_format($bags) }} bags</div>@endif
                </td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    @endif
</table>

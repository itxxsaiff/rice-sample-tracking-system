{{-- Dispatch register: same columns as the on-screen dispatch table. --}}
@php
    $hideBuyer ??= false;
    $showLoaded ??= false;
    $agreed = $dispatches->filter->isConfirmed()->groupBy('confirmed_quantity_unit')->map(fn ($rows) => $rows->sum('confirmed_quantity'));
@endphp
<table class="grid">
    <thead>
        <tr>
            <th class="no">#</th>
            <th>Dispatch</th>
            <th>Sample</th>
            @unless ($hideBuyer)<th>Buyer</th>@endunless
            <th>Sent</th>
            <th>Status</th>
            <th class="r">Agreed</th>
            @if ($showLoaded)<th class="r">Loaded</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach ($dispatches as $dispatch)
            <tr>
                <td class="no">{{ $loop->iteration }}</td>
                <td class="nowrap strong">{{ $dispatch->dispatch_number }}</td>
                <td>
                    <div class="strong">{{ $dispatch->sample->sample_number }} · {{ $dispatch->sample->variety->name }}</div>
                    <div class="sub">{{ $dispatch->sample->mill->name }}, {{ $dispatch->sample->mill->city }}</div>
                </td>
                @unless ($hideBuyer)
                    <td><div class="strong">{{ $dispatch->buyer->name }}</div><div class="sub">{{ $dispatch->buyer->city }}</div></td>
                @endunless
                <td class="nowrap">{{ $dispatch->dispatch_date->format('d M Y') }}</td>
                <td>
                    <span class="st st-{{ $dispatch->stageKey() }}">{{ $dispatch->stageLabel() }}</span>
                    @if ($dispatch->isConfirmed() && $dispatch->confirmation_date)
                        <div class="sub">{{ $dispatch->stageKey() === 'confirmed' ? '' : 'Confirmed ' }}{{ $dispatch->confirmation_date->format('d M Y') }}</div>
                    @endif
                </td>
                <td class="r nowrap">
                    @if ($dispatch->isConfirmed())
                        <div class="strong">{{ $dispatch->confirmedQuantityLabel() }}</div>
                        <div class="sub">{{ $dispatch->rateLabel() }}</div>
                        @if ($dispatch->packing_type)<div class="sub">{{ str_ends_with($dispatch->packing_type, 'kg') ? $dispatch->packing_type.' bags' : $dispatch->packing_type }}</div>@endif
                    @elseif ($dispatch->isRejected())
                        <span class="muted">Rejected {{ $dispatch->rejection_date?->format('d M Y') }}</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                @if ($showLoaded)
                    <td class="r nowrap">
                        @if ($dispatch->isConfirmed())
                            @php $loaded = $dispatch->loadedTotals()[$dispatch->confirmed_quantity_unit] ?? 0; @endphp
                            <div class="strong">{{ \App\Models\SampleDispatch::formatQuantity($loaded, $dispatch->confirmed_quantity_unit) }}</div>
                            <div class="sub">{{ $dispatch->loadings->count() }} {{ Str::plural('truck', $dispatch->loadings->count()) }} · {{ $dispatch->loadedPercent() ?? 0 }}%</div>
                        @else
                            <span class="muted">—</span>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    </tbody>
    @if ($agreed->isNotEmpty())
        <tfoot>
            <tr>
                <td colspan="{{ $hideBuyer ? 5 : 6 }}">Total agreed</td>
                <td class="r nowrap">{{ $agreed->map(fn ($qty, $unit) => \App\Models\SampleDispatch::formatQuantity($qty, $unit))->implode(', ') }}</td>
                @if ($showLoaded)
                    <td class="r nowrap">{{ collect($dispatches->filter->isConfirmed()->flatMap->loadings)->groupBy('quantity_unit')->map(fn ($rows, $unit) => \App\Models\SampleDispatch::formatQuantity($rows->sum('quantity'), $unit))->implode(', ') ?: '—' }}</td>
                @endif
            </tr>
        </tfoot>
    @endif
</table>

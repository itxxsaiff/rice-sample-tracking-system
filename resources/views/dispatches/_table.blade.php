@php $hideBuyer ??= false; $hideSample ??= false; @endphp
<div class="table-responsive">
    <table class="table table-hover table-stack align-middle">
        <thead><tr>
            <th>Dispatch</th>
            @unless ($hideSample)<th>Sample</th>@endunless
            @unless ($hideBuyer)<th>Buyer</th>@endunless
            <th>Sent</th><th>Status</th><th>Agreed</th><th class="text-end"></th>
        </tr></thead>
        <tbody>
        @foreach ($dispatches as $dispatch)
            <tr class="row-link" data-href="{{ route('dispatches.show', $dispatch) }}">
                <td class="cell-primary">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('dispatches.show', $dispatch) }}" class="cell-title mono">{{ $dispatch->dispatch_number }}</a>
                            <div class="cell-sub d-md-none text-truncate">
                                {{ $hideBuyer ? '' : $dispatch->buyer->name.' · ' }}{{ $hideSample ? '' : $dispatch->sample->sample_number.' '.$dispatch->sample->variety->name }}
                            </div>
                        </div>
                        <span class="d-md-none"><x-status-badge :status="$dispatch->stageKey()">{{ $dispatch->stageLabel() }}</x-status-badge></span>
                    </div>
                </td>
                @unless ($hideSample)
                    <td class="hide-mobile">
                        <div class="fw-medium"><span class="mono">{{ $dispatch->sample->sample_number }}</span> · {{ $dispatch->sample->variety->name }}</div>
                        <div class="cell-sub">{{ $dispatch->sample->mill->name }}, {{ $dispatch->sample->mill->city }}</div>
                    </td>
                @endunless
                @unless ($hideBuyer)
                    <td class="hide-mobile"><div class="fw-medium">{{ $dispatch->buyer->name }}</div><div class="cell-sub">{{ $dispatch->buyer->city }}</div></td>
                @endunless
                <td data-label="Sent"><span>{{ $dispatch->dispatch_date->format('d M Y') }}</span></td>
                <td class="hide-mobile"><x-status-badge :status="$dispatch->stageKey()" /></td>
                <td data-label="Agreed">
                    <span>
                        @if ($dispatch->isConfirmed())
                            <span class="fw-semibold">{{ $dispatch->confirmedQuantityLabel() }}</span>
                            <span class="cell-sub d-block">{{ $dispatch->rateLabel() }}</span>
                        @elseif ($dispatch->isRejected())
                            <span class="text-muted small text-truncate d-inline-block" style="max-width:200px">{{ $dispatch->rejection_reason }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </span>
                </td>
                <td class="cell-actions text-end text-nowrap">
                    @if ($dispatch->isPending())
                        <a href="{{ route('dispatches.respond', $dispatch) }}" class="btn btn-sm btn-soft"><i class="bi bi-reply"></i> Record response</a>
                    @else
                        <i class="bi bi-chevron-right text-muted hide-mobile d-none d-md-inline"></i>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
